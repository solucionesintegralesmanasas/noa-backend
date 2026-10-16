<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingResolution;
use App\Models\CommercialInvoice;
use App\Models\CommercialInvoiceItem;
use App\Models\CommercialInvoiceItemWithholding;
use App\Models\CommercialInvoicePayment;
use App\Models\ElectronicInvoice;
use App\Models\ThirdParty;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

/**
 * Documentos comerciales: cotizaciones (fase 1) y facturas internas (fase 2).
 *
 * La factura interna se genera completa pero queda en PENDIENTE_DIAN: el UBL
 * se guarda sin transmitir (driver nulo) hasta activar el proveedor real.
 */
class CommercialInvoiceService extends BaseService
{
    protected array $searchableFields = ['invoice_number', 'notes', 'origin_route', 'destination_route'];

    public function __construct(
        private readonly BillingResolutionService $resoluciones,
        private readonly ContratoFacturacionElectronica $facturacionElectronica
    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new CommercialInvoice;
    }

    /**
     * El afiliado solo abre por UUID sus propios documentos (por cliente).
     * Firma idéntica a la base: cambiar el tipo de retorno rompería la app.
     */
    public function findByUuid(string $uuid, array $columns = ['*'], array $relations = []): ?Model
    {
        $registro = parent::findByUuid($uuid, $columns, $relations);

        if ($registro && Auth::user()?->hasRole('AFILIADO')) {
            $tercero = $this->terceroAfiliado($registro->company_uuid);
            if ($tercero && $registro->third_party_uuid !== $tercero) {
                return null;
            }
        }

        return $registro;
    }

    /** Crea una cotización con sus ítems y totales calculados. */
    public function crearCotizacion(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $this->validarCliente($data['company_uuid'], $data['third_party_uuid']);

            $factura = CommercialInvoice::create([
                'company_uuid' => $data['company_uuid'],
                'branch_uuid' => $data['branch_uuid'] ?? null,
                'invoice_type' => CommercialInvoice::TIPO_COTIZACION,
                'status' => 'BORRADOR',
                'invoice_number' => $this->siguienteNumeroCotizacion($data['company_uuid']),
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'],
                'project_uuid' => $data['project_uuid'] ?? null,
                'contractor_uuid' => $data['contractor_uuid'] ?? null,
                'object_contract_uuid' => $data['object_contract_uuid'] ?? null,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'origin_route' => $data['origin_route'] ?? null,
                'destination_route' => $data['destination_route'] ?? null,
                'service_start' => $data['service_start'] ?? null,
                'service_end' => $data['service_end'] ?? null,
                'payment_method_uuid' => $data['payment_method_uuid'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->guardarItems($factura, $data['items']);

            return $this->getDetalle($factura->uuid);
        });
    }

    /** Crea una factura interna consumiendo consecutivo de la resolución. */
    public function crearFactura(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $this->validarCliente($data['company_uuid'], $data['third_party_uuid']);

            /** @var BillingResolution $resolucion */
            $resolucion = BillingResolution::where('uuid', $data['billing_resolution_uuid'])->first();
            if (! $resolucion || $resolucion->company_uuid !== $data['company_uuid']) {
                throw new \RuntimeException('La resolución no pertenece a la empresa.');
            }

            $this->validarServiciosNoFacturados($data['company_uuid'], $data['items']);
            $consecutivo = $this->resoluciones->tomarConsecutivo($resolucion->uuid);

            $factura = CommercialInvoice::create([
                'company_uuid' => $data['company_uuid'],
                'branch_uuid' => $data['branch_uuid'] ?? null,
                'invoice_type' => CommercialInvoice::TIPO_FACTURA,
                'status' => 'EMITIDA_INTERNA',
                'invoice_number' => $resolucion->invoice_prefix.$consecutivo,
                'billing_resolution_uuid' => $resolucion->uuid,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'],
                'project_uuid' => $data['project_uuid'] ?? null,
                'contractor_uuid' => $data['contractor_uuid'] ?? null,
                'object_contract_uuid' => $data['object_contract_uuid'] ?? null,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'origin_route' => $data['origin_route'] ?? null,
                'destination_route' => $data['destination_route'] ?? null,
                'service_start' => $data['service_start'] ?? null,
                'service_end' => $data['service_end'] ?? null,
                'origin_uuid' => $data['origin_uuid'] ?? null,
                'payment_method_uuid' => $data['payment_method_uuid'] ?? null,
                'electronic_status' => 'PENDIENTE_DIAN',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->guardarItems($factura, $data['items']);

            ElectronicInvoice::create(['invoice_uuid' => $factura->uuid, 'status' => 'PENDIENTE']);
            $this->facturacionElectronica->emitir($factura->fresh());

            return $this->getDetalle($factura->uuid);
        });
    }

    /** Convierte una cotización aprobada en factura interna. */
    public function convertirCotizacion(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var CommercialInvoice|null $cotizacion */
            $cotizacion = $this->findByUuid($uuid, ['*'], ['items.withholdings']);
            if (! $cotizacion || ! $cotizacion->esCotizacion()) {
                throw new \RuntimeException('La cotización no existe.');
            }
            if ($cotizacion->status !== 'APROBADA') {
                throw new \RuntimeException('Solo una cotización APROBADA se puede convertir en factura.');
            }

            $items = $cotizacion->items->map(fn ($item) => [
                'description' => $item->description,
                'measurement_unit_uuid' => $item->measurement_unit_uuid,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'tax_rate' => $item->tax_rate,
                'tax_type_uuid' => $item->tax_type_uuid,
                'tribute_uuid' => $item->tribute_uuid,
                'fuec_uuid' => $item->fuec_uuid,
                'control_sheet_uuid' => $item->control_sheet_uuid,
                'vehicle_uuid' => $item->vehicle_uuid,
                'withholdings' => $item->withholdings->map(fn ($w) => [
                    'withholding_uuid' => $w->withholding_uuid,
                    'withholding_type' => $w->withholding_type,
                    'withholding_rate' => $w->withholding_rate,
                ])->all(),
            ])->all();

            $factura = $this->crearFactura(array_merge($data, [
                'company_uuid' => $cotizacion->company_uuid,
                'third_party_uuid' => $cotizacion->third_party_uuid,
                'project_uuid' => $data['project_uuid'] ?? $cotizacion->project_uuid,
                'origin_uuid' => $cotizacion->uuid,
                'items' => $items,
            ]));

            $cotizacion->withoutLogging(fn () => $cotizacion->update(['status' => 'CONVERTIDA']));

            return $factura;
        });
    }

    /** Actualiza un documento en BORRADOR recalculando ítems y totales. */
    public function actualizarDocumento(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var CommercialInvoice|null $factura */
            $factura = $this->findByUuid($uuid);
            if (! $factura) {
                throw new \RuntimeException('El documento no existe.');
            }
            if ($factura->status !== 'BORRADOR') {
                throw new \RuntimeException('Solo un documento en BORRADOR se puede editar.');
            }

            $factura->update(collect($data)->except('items')->all());

            if (array_key_exists('items', $data)) {
                $factura->items()->delete();
                $this->guardarItems($factura->fresh(), $data['items']);
            }

            return $this->getDetalle($factura->uuid);
        });
    }

    /** Avanza el estado comercial según transiciones permitidas. */
    public function cambiarEstado(string $uuid, string $estado): Model
    {
        return $this->transaction(function () use ($uuid, $estado) {
            /** @var CommercialInvoice|null $factura */
            $factura = $this->findByUuid($uuid);
            if (! $factura) {
                throw new \RuntimeException('El documento no existe.');
            }

            $permitidos = $factura->esCotizacion()
                ? ['BORRADOR' => ['ENVIADA'], 'ENVIADA' => ['APROBADA', 'RECHAZADA', 'VENCIDA']]
                : ['BORRADOR' => ['EMITIDA_INTERNA', 'ANULADA'], 'EMITIDA_INTERNA' => ['PAGADA_PARCIAL', 'PAGADA', 'VENCIDA', 'ANULADA'], 'PAGADA_PARCIAL' => ['PAGADA', 'VENCIDA', 'ANULADA'], 'VENCIDA' => ['PAGADA', 'PAGADA_PARCIAL', 'ANULADA']];

            if (! in_array($estado, $permitidos[$factura->status] ?? [], true)) {
                throw new \RuntimeException("No se puede pasar de {$factura->status} a {$estado}.");
            }

            $factura->update(['status' => $estado]);

            return $this->getDetalle($factura->uuid);
        });
    }

    /** Registra un abono a una factura interna y actualiza su cartera. */
    public function registrarPago(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var CommercialInvoice|null $factura */
            $factura = $this->findByUuid($uuid);
            if (! $factura || ! $factura->esFactura()) {
                throw new \RuntimeException('La factura no existe.');
            }
            if (! in_array($factura->status, ['EMITIDA_INTERNA', 'PAGADA_PARCIAL'], true)) {
                throw new \RuntimeException('La factura no admite pagos en su estado actual.');
            }

            $monto = round((float) $data['amount'], 2);
            if ($monto <= 0 || $monto > $factura->saldo()) {
                throw new \RuntimeException('El monto del pago supera el saldo de la factura.');
            }

            CommercialInvoicePayment::create([
                'invoice_uuid' => $factura->uuid,
                'amount' => $monto,
                'payment_date' => $data['payment_date'],
                'payment_method_uuid' => $data['payment_method_uuid'] ?? null,
                'reference' => $data['reference'] ?? null,
            ]);

            $pagado = round($factura->amount_paid + $monto, 2);
            $factura->withoutLogging(fn () => $factura->update([
                'amount_paid' => $pagado,
                'status' => $pagado >= $factura->invoice_total ? 'PAGADA' : 'PAGADA_PARCIAL',
            ]));

            return $this->getDetalle($factura->uuid);
        });
    }

    /** Anula una factura o rechaza una cotización (conserva trazabilidad). */
    public function anular(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            /** @var CommercialInvoice|null $factura */
            $factura = $this->findByUuid($uuid);
            if (! $factura) {
                throw new \RuntimeException('El documento no existe.');
            }

            $factura->update(['status' => $factura->esCotizacion() ? 'RECHAZADA' : 'ANULADA']);

            return $this->getDetalle($factura->uuid);
        });
    }

    /** Solo un documento en BORRADOR se elimina (el resto conserva trazabilidad). */
    public function delete(string $uuid): bool
    {
        return $this->transaction(function () use ($uuid) {
            /** @var CommercialInvoice|null $factura */
            $factura = $this->findByUuid($uuid);
            if (! $factura || $factura->status !== 'BORRADOR') {
                return false;
            }

            return $factura->delete();
        });
    }

    /** Detalle completo con ítems, retenciones, pagos y cliente. */
    public function getDetalle(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, ['*'], [
            'items.withholdings',
            'payments',
            'customer',
            'project',
            'resolution',
            'relatedDocuments',
            'electronicInvoice',
        ]);
    }

    private function validarCliente(string $companyUuid, string $terceroUuid): void
    {
        $cliente = ThirdParty::withoutGlobalScopes()
            ->where('uuid', $terceroUuid)
            ->where('company_uuid', $companyUuid)
            ->first();

        if (! $cliente || ! $cliente->is_active || ! $cliente->is_customer) {
            throw new \RuntimeException('El cliente no existe, está inactivo o no es cliente.');
        }
    }

    private function validarServiciosNoFacturados(string $companyUuid, array $items): void
    {
        $fuecs = array_filter(array_column($items, 'fuec_uuid'));
        $planillas = array_filter(array_column($items, 'control_sheet_uuid'));

        foreach (['fuec_uuid' => $fuecs, 'control_sheet_uuid' => $planillas] as $campo => $uuids) {
            if (empty($uuids)) {
                continue;
            }

            $repetido = CommercialInvoiceItem::whereIn($campo, $uuids)
                ->whereHas('invoice', fn ($q) => $q->where('company_uuid', $companyUuid)
                    ->where('invoice_type', CommercialInvoice::TIPO_FACTURA)
                    ->whereNotIn('status', ['ANULADA', 'BORRADOR']))
                ->exists();

            if ($repetido) {
                throw new \RuntimeException('Uno de los servicios ya fue facturado.');
            }
        }
    }

    private function siguienteNumeroCotizacion(string $companyUuid): string
    {
        for ($intento = 0; $intento < 3; $intento++) {
            $seq = CommercialInvoice::where('company_uuid', $companyUuid)
                ->where('invoice_type', CommercialInvoice::TIPO_COTIZACION)
                ->lockForUpdate()
                ->count() + 1;
            $numero = 'COT-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

            $existe = CommercialInvoice::where('company_uuid', $companyUuid)
                ->where('invoice_type', CommercialInvoice::TIPO_COTIZACION)
                ->where('invoice_number', $numero)
                ->exists();

            if (! $existe) {
                return $numero;
            }
        }

        throw new QueryException('', [], new \Exception('No se pudo asignar el consecutivo de cotización.'));
    }

    private function guardarItems(CommercialInvoice $factura, array $items): void
    {
        if (empty($items)) {
            throw new \RuntimeException('El documento debe tener al menos un ítem.');
        }

        $lineas = [];
        $orden = 1;

        foreach ($items as $datos) {
            $linea = CalculaTotales::calcularLinea($datos);

            $item = CommercialInvoiceItem::create([
                'invoice_uuid' => $factura->uuid,
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
                'fuec_uuid' => $datos['fuec_uuid'] ?? null,
                'control_sheet_uuid' => $datos['control_sheet_uuid'] ?? null,
                'vehicle_uuid' => $datos['vehicle_uuid'] ?? null,
            ]);

            $retencion = 0.0;
            foreach ($datos['withholdings'] ?? [] as $ret) {
                $monto = round($linea['base'] * (float) $ret['withholding_rate'] / 100, 2);
                $retencion += $monto;
                CommercialInvoiceItemWithholding::create([
                    'invoice_item_uuid' => $item->uuid,
                    'withholding_uuid' => $ret['withholding_uuid'] ?? null,
                    'withholding_type' => $ret['withholding_type'],
                    'withholding_rate' => $ret['withholding_rate'],
                    'amount' => $monto,
                ]);
            }

            $lineas[] = [
                'base' => $linea['base'],
                'descuento' => (float) ($datos['discount_amount'] ?? 0),
                'impuesto' => $linea['impuesto'],
                'retencion' => round($retencion, 2),
            ];
        }

        $totales = CalculaTotales::sumarTotales($lineas);
        $factura->withoutLogging(fn () => $factura->update([
            'subtotal' => $totales['subtotal'],
            'total_discount' => $totales['descuento'],
            'total_tax' => $totales['impuesto'],
            'total_withholding' => $totales['retencion'],
            'invoice_total' => $totales['total'],
        ]));
    }

    private function terceroAfiliado(string $companyUuid): ?string
    {
        $tercero = request()->attributes->get('current_third_party_uuid');
        if ($tercero) {
            return $tercero;
        }

        $empresa = Auth::user()?->companies()->where('company_user.company_uuid', $companyUuid)->first()
            ?? Auth::user()?->companies()->first();

        return $empresa?->pivot->third_party_uuid;
    }
}
