<?php

declare(strict_types=1);

namespace App\Services\Billing;

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
 * operation_type, payment_method_code, unit_measure_code, standard_code,
 * tax_code, municipality_code, type_document_ids, customer.*).
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

    /** Códigos de retención (autorretención) de Factus. */
    private const RETENCIONES = [
        'RENTA' => '06',
        'RENTA_FUENTE' => '06',
        'RETEFUENTE' => '06',
        'IVA' => '05',
        'ICA' => '07',
    ];

    public function __construct(
        /** Overrides por empresa leídos de billing_providers.settings. */
        private readonly array $ajustes = []
    ) {}

    private function valor(string $clave, int|string $porDefecto): int|string
    {
        return Arr::get($this->ajustes, $clave) ?? $porDefecto;
    }

    /** Punto de entrada de una factura de venta de servicios. */
    public function factura(Model $factura): array
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

        $this->adjuntarRango($payload);
        $this->adjuntarObservacion($payload, $factura->notes);

        return $payload;
    }

    /** Notas crédito y débito (misma estructura salvo el endpoint). */
    public function nota(Model $nota, string $customizationId = '20'): array
    {
        $nota->loadMissing('items', 'items.withholdings', 'invoice.customer', 'invoice.customer.municipality');

        $factura = $nota->invoice;

        $payload = [
            'reference_code' => $this->referencia((string) $nota->note_number, (string) $nota->uuid),
            'customization_id' => $customizationId,
            'bill_number' => (string) $factura->invoice_number,
            'payment_details' => [$this->detallePago($nota)],
            'customer' => $this->cliente($factura->customer),
            'items' => $this->lineas($nota->items),
        ];

        if ($nota->correction_concept_code !== null && $nota->correction_concept_code !== '') {
            $payload['correction_concept_code'] = (string) $nota->correction_concept_code;
        }

        $this->adjuntarRango($payload);
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

    private function adjuntarRango(array &$payload): void
    {
        $rango = Arr::get($this->ajustes, 'numbering_range_id');
        if ($rango !== null && $rango !== '') {
            $payload['numbering_range_id'] = (int) $rango;
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
            'payment_method_code' => (string) $this->valor('payment_method_code', '10'),
            'amount' => $this->dinero($this->totalPagar($documento)),
        ];

        if ($credito) {
            $detalle['due_date'] = $documento->due_date->toDateString();
        }

        return $detalle;
    }

    private function totalPagar(Model $documento): float
    {
        return (float) ($documento->invoice_total ?? $documento->total ?? 0);
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

    private function adjuntarImpuestos(array &$linea, mixed $item): void
    {
        $impuesto = (float) ($item->tax_amount ?? 0);
        $tarifa = (float) ($item->tax_rate ?? 0);

        if ($impuesto <= 0) {
            return;
        }

        $linea['taxes'] = [[
            'code' => (string) $this->valor('tax_code', '01'),
            'rate' => number_format($tarifa, 2, '.', ''),
        ]];
    }

    private function adjuntarRetenciones(array &$linea, mixed $item): void
    {
        $withholdings = $item->relationLoaded('withholdings') ? $item->withholdings : null;
        if (! $withholdings || $withholdings->isEmpty()) {
            return;
        }

        $retenciones = [];
        foreach ($withholdings as $retencion) {
            $tipo = mb_strtoupper((string) ($retencion->withholding_type ?? 'RENTA'));
            $retenciones[] = [
                'code' => self::RETENCIONES[$tipo] ?? (string) $this->valor('withholding_code', '06'),
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
