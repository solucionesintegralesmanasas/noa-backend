<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CommercialInvoice;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use App\Models\DebitNoteItemWithholding;
use App\Services\ActorScope\AlcanceDelActor;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** Notas débito comerciales con transmisión electrónica opcional. */
class DebitNoteService extends BaseService
{
    protected array $searchableFields = ['note_number', 'observation'];

    protected array $sortableFields = [
        'issue_date' => 'debit_notes.issue_date',
        'note_number' => 'debit_notes.note_number',
        'total' => 'debit_notes.total',
        'status' => 'debit_notes.status',
        'electronic_status' => 'debit_notes.electronic_status',
        'created_at' => 'debit_notes.created_at',
    ];

    public function __construct(private readonly ContratoNotasElectronica $emisionElectronica)
    {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new DebitNote;
    }

    /**
     * Lista notas débito aplicando contexto de empresa, alcance del actor,
     * filtros exactos, búsqueda libre (incluyendo número de factura) y ordenamiento server-side.
     */
    public function listar(
        array $filtros = [],
        int $perPage = 15,
        ?string $search = null,
        ?string $sortBy = null,
        ?string $sortOrder = 'desc'
    ): LengthAwarePaginator {
        $query = $this->query()->with(['invoice', 'customer']);

        $companyUuid = $filtros['company_uuid'] ?? (app()->bound('current_company_uuid') ? app('current_company_uuid') : null);
        if ($companyUuid !== null && $companyUuid !== '') {
            $this->applyCompanyFilter($query, (string) $companyUuid);
        }

        foreach (['status', 'electronic_status', 'invoice_uuid', 'third_party_uuid'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->where('debit_notes.' . $campo, $filtros[$campo]);
            }
        }

        $termino = trim((string) $search);
        if ($termino !== '') {
            $query->where(function ($q) use ($termino) {
                $q->where('debit_notes.note_number', 'like', "%{$termino}%")
                    ->orWhere('debit_notes.observation', 'like', "%{$termino}%")
                    ->orWhereHas('invoice', fn ($iq) => $iq->where('invoice_number', 'like', "%{$termino}%"));
            });
        }

        if ($sortBy && array_key_exists($sortBy, $this->sortableFields)) {
            $this->applySorting($query, $sortBy, $sortOrder);
        } else {
            $query->orderByDesc('debit_notes.issue_date')
                ->orderByDesc('debit_notes.id');
        }

        return $query->paginate($perPage);
    }

    public function crearNota(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $queryFactura = CommercialInvoice::where('uuid', $data['invoice_uuid']);
            app(AlcanceDelActor::class)->aislarPorAfiliado($queryFactura, new CommercialInvoice);

            /** @var CommercialInvoice|null $factura */
            $factura = $queryFactura->first();
            $companyCtx = app()->bound('current_company_uuid') ? app('current_company_uuid') : null;
            if (! $factura || ! $factura->esFactura() || ($companyCtx && $factura->company_uuid !== $companyCtx)) {
                throw new \RuntimeException('La factura afectada no existe.');
            }
            if (! in_array($factura->status, ['EMITIDA_INTERNA', 'PAGADA_PARCIAL', 'VENCIDA', 'PAGADA'], true)) {
                throw new \RuntimeException('La factura no admite notas débito en su estado actual.');
            }

            $nota = DebitNote::create([
                'company_uuid' => $factura->company_uuid,
                'invoice_uuid' => $factura->uuid,
                'third_party_uuid' => $factura->third_party_uuid,
                'note_number' => $this->siguienteNumero($factura->company_uuid),
                'correction_concept_code' => $data['correction_concept_code'],
                'observation' => $data['observation'] ?? null,
                'issue_date' => $data['issue_date'],
                'payment_method_uuid' => $data['payment_method_uuid'] ?? null,
                'status' => 'BORRADOR',
                'electronic_status' => 'PENDIENTE_DIAN',
            ]);

            $this->guardarItems($nota, $data['items']);

            return $this->detalle($nota->uuid);
        });
    }

    /** Emite la nota y aumenta el total de la factura. */
    public function emitirNota(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            /** @var DebitNote|null $nota */
            $nota = $this->findByUuid($uuid, ['*'], ['items']);
            if (! $nota) {
                throw new \RuntimeException('La nota débito no existe.');
            }
            if ($nota->status !== 'BORRADOR') {
                throw new \RuntimeException('Solo una nota en BORRADOR se puede emitir.');
            }

            /** @var CommercialInvoice $factura */
            $factura = CommercialInvoice::where('uuid', $nota->invoice_uuid)->first();
            $nuevoTotal = round($factura->invoice_total + $nota->total, 2);
            $factura->withoutLogging(fn () => $factura->update([
                'invoice_total' => $nuevoTotal,
                'status' => $nuevoTotal <= $factura->amount_paid ? 'PAGADA' : ($factura->amount_paid > 0 ? 'PAGADA_PARCIAL' : $factura->status),
            ]));

            $nota->update(['status' => 'EMITIDA_INTERNA']);

            $this->emisionElectronica->emitirNota($nota->fresh());

            return $this->detalle($nota->uuid);
        });
    }

    public function anularNota(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            /** @var DebitNote|null $nota */
            $nota = $this->findByUuid($uuid);
            if (! $nota) {
                throw new \RuntimeException('La nota débito no existe.');
            }
            if ($nota->status !== 'BORRADOR') {
                throw new \RuntimeException('Solo una nota en BORRADOR se puede anular.');
            }

            $nota->update(['status' => 'ANULADA']);

            return $this->detalle($nota->uuid);
        });
    }

    public function detalle(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, ['*'], ['items.withholdings', 'invoice']);
    }

    private function siguienteNumero(string $companyUuid): string
    {
        for ($intento = 0; $intento < 3; $intento++) {
            $seq = DebitNote::where('company_uuid', $companyUuid)->lockForUpdate()->count() + 1;
            $numero = 'ND-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            if (! DebitNote::where('company_uuid', $companyUuid)->where('note_number', $numero)->exists()) {
                return $numero;
            }
        }

        throw new QueryException('', [], new \Exception('No se pudo asignar el consecutivo de nota débito.'));
    }

    private function guardarItems(DebitNote $nota, array $items): void
    {
        if (empty($items)) {
            throw new \RuntimeException('La nota debe tener al menos un ítem.');
        }

        $lineas = [];
        $orden = 1;

        foreach ($items as $datos) {
            $linea = CalculaTotales::calcularLinea($datos);

            $item = DebitNoteItem::create([
                'note_uuid' => $nota->uuid,
                'order_index' => $orden++,
                'description' => $datos['description'],
                'measurement_unit_uuid' => $datos['measurement_unit_uuid'] ?? null,
                'quantity' => $datos['quantity'] ?? 1,
                'unit_price' => $datos['unit_price'] ?? 0,
                'discount_amount' => $datos['discount_amount'] ?? 0,
                'tax_rate' => $datos['tax_rate'] ?? 0,
                'tax_amount' => $linea['impuesto'],
                'line_total' => $linea['total'],
                'tax_type_uuid' => $datos['tax_type_uuid'] ?? null,
                'tribute_uuid' => $datos['tribute_uuid'] ?? null,
            ]);

            $retencion = 0.0;
            foreach ($datos['withholdings'] ?? [] as $ret) {
                $monto = round($linea['base'] * (float) $ret['withholding_rate'] / 100, 2);
                $retencion += $monto;
                DebitNoteItemWithholding::create([
                    'note_item_uuid' => $item->uuid,
                    'withholding_uuid' => $ret['withholding_uuid'] ?? null,
                    'withholding_type' => $ret['withholding_type'],
                    'withholding_rate' => $ret['withholding_rate'],
                    'amount' => $monto,
                ]);
            }

            $lineas[] = ['base' => $linea['base'], 'descuento' => (float) ($datos['discount_amount'] ?? 0), 'impuesto' => $linea['impuesto'], 'retencion' => round($retencion, 2)];
        }

        $totales = CalculaTotales::sumarTotales($lineas);
        $nota->withoutLogging(fn () => $nota->update([
            'subtotal' => $totales['subtotal'],
            'total_discount' => $totales['descuento'],
            'total_tax' => $totales['impuesto'],
            'total_withholding' => $totales['retencion'],
            'total' => $totales['total'],
        ]));
    }
}
