<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\PaymentMethod;
use App\Models\ThirdParty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Traduce los documentos internos (facturas y notas) al payload JSON de Factus.
 *
 * Enfoque exclusivo en SERVICIOS (factura de venta, documento "01"): los ítems
 * se describen con unidad de medida de servicio y estándar UNSPSC/código propio.
 * Factus calcula los totales y asigna el consecutivo dentro del rango
 * (`numbering_range_id`), así que NO se envían totales legales ni numeración.
 *
 * Los códigos de catálogo DIAN usan valores por defecto y se sobreescriben por
 * empresa con `billing_providers.settings` (claves: numbering_range_id,
 * numbering_ranges.{bills|credit_notes|debit_notes}, operation_type,
 * payment_method_code, unit_measure_code, standard_code, tax_code,
 * municipality_code, type_document_ids, customer.*, send_self_withholdings).
 *
 * Referencia: https://developers.factus.com.co (API v2).
 */
class FactusMapeo
{
    /**
     * Códigos de identificación de Factus según catálogo DIAN.
     *
     * @var array<string, string>
     */
    private const DOC_IDENTIFICACION = [
        'CC' => '13', // Cédula de ciudadanía
        'NIT' => '31', // NIT (el DV viaja en campo aparte)
        'CE' => '22', // Cédula de extranjería
        'PASAPORTE' => '41',
        'TI' => '12', // Tarjeta de identidad
        'REGISTRO_CIVIL' => '11',
    ];

    /**
     * Códigos de autorretención aceptados por Factus v2 (tabla "Códigos de
     * retenciones"): solo 05 (ReteIVA) y 06 (ReteRenta). ReteICA no existe.
     */
    private const RETENCIONES = [
        'RENTA' => '06',
        'RENTA_FUENTE' => '06',
        'RETEFUENTE' => '06',
        'IVA' => '05',
        'RETEIVA' => '05',
    ];

    /** customization_id por defecto de cada nota (20 crédito, 30 débito). */
    public const NOTA_CREDITO = '20';

    public const NOTA_DEBITO = '30';

    public function __construct(
        /** Overrides por empresa leídos de billing_providers.settings. */
        private readonly array $ajustes = []
    ) {}

    private function valor(string $clave, int|string $porDefecto): int|string
    {
        return Arr::get($this->ajustes, $clave) ?? $porDefecto;
    }

    /** Punto de entrada de una factura de venta de servicios. */
    public function factura(Model $factura, ?int $rango = null): array
    {
        $factura->loadMissing('items', 'items.withholdings', 'customer', 'customer.municipality');

        $payload = [
            'reference_code' => $this->referencia((string) $factura->invoice_number, (string) $factura->uuid),
            'document' => '01',
            'operation_type' => (string) $this->valor('operation_type', '10'),
            'payment_details' => [$this->detallePago($factura)],
            'customer' => $this->cliente($factura->customer),
            'items' => $this->lineas($factura->items),
        ];

        $this->adjuntarRango($payload, $rango);
        $this->adjuntarObservacion($payload, $factura->notes);

        return $payload;
    }

    /** Notas crédito (customization_id 20) y débito (30) con referencia a factura. */
    public function nota(Model $nota, string $customizationId = self::NOTA_CREDITO, ?int $rango = null): array
    {
        $nota->loadMissing('items', 'items.withholdings', 'invoice.customer', 'invoice.customer.municipality');

        $factura = $nota->invoice;
        if (! $factura) {
            throw new \RuntimeException('La nota no está asociada a una factura.');
        }

        // Factus exige el número que ÉL asignó a la factura (p. ej. SETP990000550).
        $numeroFactura = (string) ($factura->provider_document_id ?: $factura->invoice_number);

        $concepto = trim((string) ($nota->correction_concept_code ?? ''));
        if ($concepto === '') {
            throw new \RuntimeException('La nota no tiene concepto de corrección (correction_concept_code), obligatorio para Factus.');
        }

        $payload = [
            'reference_code' => $this->referencia((string) $nota->note_number, (string) $nota->uuid),
            'correction_concept_code' => $concepto,
            'customization_id' => $customizationId,
            'bill_number' => $numeroFactura,
            'payment_details' => [$this->detallePago($nota)],
            'customer' => $this->cliente($factura->customer),
            'items' => $this->lineas($nota->items),
        ];

        $this->adjuntarRango($payload, $rango);
        $this->adjuntarObservacion($payload, $nota->observation);

        return $payload;
    }

    public function cliente(?ThirdParty $tercero): array
    {
        if (! $tercero) {
            throw new \RuntimeException('El documento no tiene adquiriente asociado.');
        }

        $juridica = $tercero->person_type === 'JURIDICA';

        $cliente = [
            'identification_document_code' => $this->codigoDocumento($tercero->person_type),
            'identification' => (string) $tercero->document_number,
            'legal_organization_code' => $juridica ? '1' : '2',
            'tribute_code' => (string) $this->valor('customer.tribute_code', 'ZZ'),
            'country_code' => (string) $this->valor('customer.country_code', 'CO'),
            'responsibilities' => Arr::wrap($this->valor('customer.responsibilities', 'R-99-PN')),
        ];

        // NIT: el DV va en su campo exclusivo y sin guion.
        if ($juridica && $tercero->nit_check_digit !== null && $tercero->nit_check_digit !== '') {
            $cliente['dv'] = (string) $tercero->nit_check_digit;
        }

        if ($juridica) {
            $cliente['company'] = $tercero->company_name ?: $tercero->trade_name ?: '';
            if ($tercero->trade_name) {
                $cliente['trade_name'] = $tercero->trade_name;
            }
        } else {
            $cliente['names'] = trim(implode(' ', array_filter([$tercero->first_name, $tercero->last_name])));
        }

        $this->adjuntarOpcional($cliente, 'address', $tercero->address);
        $this->adjuntarOpcional($cliente, 'email', $tercero->email);
        $this->adjuntarOpcional($cliente, 'phone', $tercero->phone);

        $cliente['municipality_code'] = (string) $this->municipio($tercero);

        return $cliente;
    }

    /** @param iterable $items CommercialInvoiceItem|CreditNoteItem|DebitNoteItem */
    public function lineas(iterable $items): array
    {
        $lineas = [];

        foreach ($items as $indice => $item) {
            $cantidad = (float) ($item->quantity ?? 1);
            $precio = (float) ($item->unit_price ?? 0);

            $linea = [
                'code_reference' => 'ITEM'.str_pad((string) ($item->order_index ?? $indice + 1), 3, '0', STR_PAD_LEFT),
                'name' => Str::limit((string) ($item->description ?? ''), 200),
                'quantity' => number_format($cantidad, 2, '.', ''),
                'price' => $this->dinero($precio),
                'unit_measure_code' => (string) $this->valor('unit_measure_code', '94'),
                'standard_code' => (string) $this->valor('standard_code', '999'),
            ];

            $this->adjuntarDescuento($linea, $item, $cantidad, $precio);
            $this->adjuntarImpuestos($linea, $item);
            $this->adjuntarRetenciones($linea, $item);

            $lineas[] = $linea;
        }

        return $lineas;
    }

    // ─── apoyo ────────────────────────────────────────────────────────────

    private function adjuntarRango(array &$payload, ?int $rango): void
    {
        if ($rango !== null && $rango > 0) {
            $payload['numbering_range_id'] = $rango;
        }
    }

    private function adjuntarObservacion(array &$payload, mixed $observacion): void
    {
        if (is_string($observacion) && trim($observacion) !== '') {
            $payload['observation'] = Str::limit(trim($observacion), 500);
        }
    }

    private function adjuntarOpcional(array &$destino, string $clave, mixed $valor): void
    {
        if (is_string($valor) && trim($valor) !== '') {
            $destino[$clave] = trim($valor);
        }
    }

    private function detallePago(Model $documento): array
    {
        $credito = $documento->due_date !== null;

        $detalle = [
            'payment_form' => $credito ? '2' : '1',
            'payment_method_code' => $this->codigoMedioPago($documento),
            'amount' => $this->dinero($this->totalPagar($documento)),
        ];

        if ($credito) {
            $detalle['due_date'] = $documento->due_date->toDateString();
        }

        return $detalle;
    }

    /** Medio de pago elegido en el documento (dian_code); si no, el de la empresa. */
    private function codigoMedioPago(Model $documento): string
    {
        $uuid = $documento->payment_method_uuid ?? null;
        if (is_string($uuid) && $uuid !== '') {
            $codigo = PaymentMethod::where('uuid', $uuid)->value('dian_code');
            if (is_string($codigo) && $codigo !== '') {
                return $codigo;
            }
        }

        return (string) $this->valor('payment_method_code', '10');
    }

    /**
     * Monto del medio de pago = total que calcula Factus: base neta + impuestos.
     * Las retenciones que practica el cliente NO restan del total del documento
     * electrónico (Factus valida que la suma de payment_details cuadre).
     */
    private function totalPagar(Model $documento): float
    {
        if ($documento->subtotal !== null) {
            return round((float) $documento->subtotal + (float) ($documento->total_tax ?? 0), 2);
        }

        $total = (float) ($documento->invoice_total ?? $documento->total ?? 0);

        return round($total + (float) ($documento->total_withholding ?? 0), 2);
    }

    private function adjuntarDescuento(array &$linea, mixed $item, float $cantidad, float $precio): void
    {
        $descuento = (float) ($item->discount_amount ?? 0);
        if ($descuento <= 0) {
            return;
        }

        $base = $cantidad * $precio;
        if ($base > 0) {
            $linea['discount_rate'] = number_format(min(100, ($descuento / $base) * 100), 2, '.', '');
        } else {
            $linea['discount_amount'] = $this->dinero($descuento);
        }
    }

    /**
     * `items.*.taxes` es obligatorio en Factus v2: con tarifa > 0 se envía el
     * IVA (código 01) y, sin tarifa, el servicio va como excluido
     * (`is_excluded: true`), caso del transporte terrestre (art. 476 E.T.).
     */
    private function adjuntarImpuestos(array &$linea, mixed $item): void
    {
        $tarifa = (float) ($item->tax_rate ?? 0);

        if ($tarifa <= 0) {
            $linea['taxes'] = [['is_excluded' => true]];

            return;
        }

        $linea['taxes'] = [[
            'code' => (string) $this->valor('tax_code', '01'),
            'rate' => number_format($tarifa, 2, '.', ''),
        ]];
    }

    /**
     * `withholding_taxes` en Factus son AUTORRETENCIONES (las que el emisor se
     * practica a sí mismo), no las retenciones que aplica el cliente. Solo se
     * envían si la empresa es autorretenedora (`send_self_withholdings`) y con
     * los códigos que acepta Factus (05/06).
     */
    private function adjuntarRetenciones(array &$linea, mixed $item): void
    {
        if (! filter_var(Arr::get($this->ajustes, 'send_self_withholdings', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $withholdings = $item->relationLoaded('withholdings') ? $item->withholdings : null;
        if (! $withholdings || $withholdings->isEmpty()) {
            return;
        }

        $retenciones = [];
        foreach ($withholdings as $retencion) {
            $tipo = mb_strtoupper((string) ($retencion->withholding_type ?? 'RENTA'));
            $codigo = self::RETENCIONES[$tipo] ?? null;
            if ($codigo === null) {
                continue; // p. ej. ReteICA: Factus no la recibe.
            }
            $retenciones[] = [
                'code' => $codigo,
                'rate' => number_format((float) ($retencion->withholding_rate ?? 0), 2, '.', ''),
            ];
        }

        if ($retenciones !== []) {
            $linea['withholding_taxes'] = $retenciones;
        }
    }

    private function codigoDocumento(?string $tipoPersona): string
    {
        if ($tipoPersona === 'JURIDICA') {
            return (string) $this->valor('type_document_ids.juridica', self::DOC_IDENTIFICACION['NIT']);
        }

        return (string) $this->valor('type_document_ids.natural', self::DOC_IDENTIFICACION['CC']);
    }

    /** Municipio: código DIAN (dane_code) del tercero, con override por empresa. */
    private function municipio(ThirdParty $tercero): string
    {
        $override = Arr::get($this->ajustes, 'municipality_code');
        if ($override !== null && $override !== '') {
            return (string) $override;
        }

        $codigo = $tercero->relationLoaded('municipality') ? $tercero->municipality?->dane_code : null;

        return (string) ($codigo ?: $this->valor('municipality_code', '11001'));
    }

    /** "TSB-00000001" → "TSB-00000001"; sin número → el uuid (idempotencia). */
    private function referencia(string $numero, string $uuid): string
    {
        $numero = trim($numero);

        return $numero !== '' ? $numero : $uuid;
    }

    private function dinero(mixed $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, '.', '');
    }
}
