<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CommercialInvoice;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use App\Models\DebitNoteItemWithholding;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** Notas débito internas que aumentan el total de una factura. */
class DebitNoteService extends BaseService
{
    protected array $searchableFields = ['note_number', 'observation'];

    protected function getModelInstance(): Model
    {
        return new DebitNote;
    }

    public function crearNota(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            /** @var CommercialInvoice|null $factura */
            $factura = CommercialInvoice::where('uuid', $data['invoice_uuid'])->first();
            if (! $factura || ! $factura->esFactura()) {
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
