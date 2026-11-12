<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CommercialInvoice;
use App\Models\ThirdParty;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mapeador que transforma los modelos internos al formato JSON de ApiDian (FACTW).
 *
 * FACTW usa sus propios ids de catálogo, no los códigos DIAN. Los ids de
 * municipios viven en `config/apidian-catalogs.php` (generado por
 * `billing:sync-apidian-catalogs`); el resto de catálogos son constantes
 * pequeñas y estables aquí.
 *
 * ApiDian no gestiona retenciones: `payable_amount = tax_inclusive_amount` y no
 * se envían claves withholding_*. La retención se conserva solo en NOA (la
 * retención no hace parte del pagaré de la factura ante la DIAN).
 */
class ApidianMapeo
{
    /** prefix de type_of_documents → id de FACTW (type_document_identifications). */
    private const DOC_IDENTIFICACION = [
        'CC' => 3,   // Cédula de ciudadanía
        'CE' => 5,   // Cédula de extranjería
        'NIT' => 6,  // NIT
        'TI' => 2,   // Tarjeta de identidad
        'PA' => 7,   // Pasaporte
    ];

    /** tax_regime de NOA → id de FACTW (type_regimes). */
    private const REGIMENES = [
        '48' => 1,   // Responsable de IVA
        '49' => 2,   // No responsable de IVA
    ];

    /** tax_responsibilities.code de NOA → id de FACTW (type_liabilities). */
    private const RESPONSABILIDADES = [
        'O-13' => 7,     // Gran contribuyente
        'O-15' => 9,     // Autorretenedor
        'O-23' => 14,    // Agente de retención IVA
        'O-47' => 112,   // Régimen Simple de Tributación
        'R-99-PN' => 117, // No responsable (persona natural)
    ];

    /** measurement_units.code de NOA → id de FACTW (unit_measures). */
    private const UNIDADES = [
        '94' => 70,    // unidad
        'HUR' => 730,  // hora
        'ZZ' => 1093,  // mutuamente definido
    ];

    public function __construct(
        private readonly array $ajustes = []
    ) {}

    /** Convierte CommercialInvoice al payload de ApiDian (sin `send`: lo fija el driver). */
    public function map(CommercialInvoice $factura): array
    {
        $factura->loadMissing([
            'customer.documentType',
            'customer.municipality',
            'customer.taxResponsibility',
            'resolution',
            'paymentMethod',
            'items.measurementUnit',
        ]);

        return [
            'number' => $this->consecutivo($factura),
            'type_document_id' => 1,
            'resolution_number' => (string) ($factura->resolution?->resolution_number ?? '0'),
            'date' => $factura->issue_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'time' => $factura->issue_date?->format('H:i:s') ?? now()->format('H:i:s'),
            'customer' => $this->mapearCliente($factura->customer),
            'payment_form' => $this->mapearFormaPago($factura),
            'legal_monetary_totals' => $this->mapearTotales($factura),
            'tax_totals' => $this->mapearImpuestos($factura),
            'invoice_lines' => $this->mapearLineas($factura->items),
        ];
    }

    // ─── Cliente / Comprador ──────────────────────────────────────────────

    private function mapearCliente(?ThirdParty $cliente): array
    {
        if (! $cliente) {
            return [];
        }

        $tipoDoc = self::DOC_IDENTIFICACION[$cliente->documentType?->prefix ?? '']
            ?? ($cliente->person_type === 'JURIDICA' ? self::DOC_IDENTIFICACION['NIT'] : self::DOC_IDENTIFICACION['CC']);

        $regimen = self::REGIMENES[$cliente->tax_regime ?? ''] ?? 2;
        $responsabilidad = self::RESPONSABILIDADES[$cliente->taxResponsibility?->code ?? ''] ?? 7;

        $clienteArray = [
            'identification_number' => preg_replace('/\D/', '', (string) ($cliente->document_number ?? '')),
            'name' => trim($cliente->company_name ?: $cliente->trade_name
                ?: trim(($cliente->first_name ?? '').' '.($cliente->last_name ?? ''))),
            'type_document_identification_id' => $tipoDoc,
            'type_organization_id' => $cliente->person_type === 'JURIDICA' ? 1 : 2,
            'type_liability_id' => $responsabilidad,
            'municipality_id' => $this->municipalityId($cliente),
            'type_regime_id' => $regimen,
        ];

        if ($tipoDoc === self::DOC_IDENTIFICACION['NIT'] && ($dv = (string) ($cliente->nit_check_digit ?? '')) !== '') {
            $clienteArray['dv'] = $dv;
        }

        if (($email = trim((string) ($cliente->email ?? ''))) !== '') {
            $clienteArray['email'] = $email;
        }

        return $clienteArray;
    }

    /** Id de FACTW del municipio a partir del código DANE de NOA. */
    private function municipalityId(ThirdParty $cliente): int
    {
        $dane = (string) ($cliente->municipality?->dane_code ?? '');
        $mapa = config('apidian-catalogs.municipalities', []);
        $id = $dane !== '' ? ($mapa[$dane] ?? null) : null;
        $id ??= $this->ajustes['municipality_id'] ?? null;

        if ($id === null) {
            throw new \RuntimeException(
                'El municipio del cliente '.($dane !== '' ? "(DANE {$dane})" : '(sin municipio)')
                .' no tiene equivalencia en ApiDian. Actualiza el catálogo con billing:sync-apidian-catalogs o configura municipality_id.'
            );
        }

        return (int) $id;
    }

    // ─── Forma de pago ────────────────────────────────────────────────────

    private function mapearFormaPago(CommercialInvoice $documento): array
    {
        $codigoMedio = (string) ($documento->paymentMethod?->dian_code ?? '');
        $medioId = $codigoMedio === 'ZZZ' ? 75 : ((int) $codigoMedio ?: 10);

        $forma = [
            'payment_form' => $documento->due_date !== null ? 2 : 1,
            'payment_method_id' => $medioId,
        ];

        if ($documento->due_date !== null) {
            $forma['payment_due_date'] = $documento->due_date->format('Y-m-d');
        }

        return $forma;
    }

    // ─── Totales legales ──────────────────────────────────────────────────

    private function mapearTotales(CommercialInvoice $documento): array
    {
        // NOA: subtotal ya es neto de descuentos de línea.
        $base = (float) ($documento->subtotal ?? 0);
        $descuentos = (float) ($documento->total_discount ?? 0);
        $impuestos = (float) ($documento->total_tax ?? 0);

        $lineExtension = $base + $descuentos;
        $taxInclusive = $base + $impuestos;

        return [
            'line_extension_amount' => number_format($lineExtension, 2, '.', ''),
            'tax_exclusive_amount' => number_format($base, 2, '.', ''),
            'tax_inclusive_amount' => number_format($taxInclusive, 2, '.', ''),
            'allowance_total_amount' => number_format($descuentos, 2, '.', ''),
            'charge_total_amount' => '0.00',
            'pre_paid_amount' => '0.00',
            'payable_amount' => number_format($taxInclusive, 2, '.', ''),
        ];
    }

    // ─── Impuestos ────────────────────────────────────────────────────────

    private function mapearImpuestos(CommercialInvoice $documento): array
    {
        $impuesto = (float) ($documento->total_tax ?? 0);
        if ($impuesto <= 0) {
            return [];
        }

        return [[
            'tax_id' => 1, // IVA
            'tax_amount' => number_format($impuesto, 2, '.', ''),
            'percent' => number_format((float) ($this->ajustes['tax_percent'] ?? 19), 2, '.', ''),
            'taxable_amount' => number_format((float) ($documento->subtotal ?? 0), 2, '.', ''),
        ]];
    }

    // ─── Líneas de factura ────────────────────────────────────────────────

    private function mapearLineas(?iterable $items): array
    {
        if (! $items) {
            return [];
        }

        $lineas = [];
        foreach ($items as $indice => $item) {
            $cantidad = (float) ($item->quantity ?? 1);
            $precio = (float) ($item->unit_price ?? 0);
            $bruto = round($cantidad * $precio, 2);
            $baseLinea = round($bruto - (float) ($item->discount_amount ?? 0), 2);
            $impuestoLinea = (float) ($item->tax_amount ?? 0);
            $tasaImpuesto = (float) ($item->tax_rate ?? 0);

            $lineas[] = [
                'unit_measure_id' => $this->unidadId($item),
                'invoiced_quantity' => number_format($cantidad, 2, '.', ''),
                'line_extension_amount' => number_format($bruto, 2, '.', ''),
                'free_of_charge_indicator' => false,
                'description' => Str::limit((string) ($item->description ?? 'Servicio'), 200),
                'code' => (string) ($item->code ?? 'ITEM'.str_pad((string) ($item->order_index ?? $indice + 1), 3, '0', STR_PAD_LEFT)),
                'type_item_identification_id' => 4,
                'price_amount' => number_format($precio, 2, '.', ''),
                'base_quantity' => number_format($cantidad, 2, '.', ''),
                'tax_totals' => $impuestoLinea > 0 ? [[
                    'tax_id' => 1,
                    'tax_amount' => number_format($impuestoLinea, 2, '.', ''),
                    'percent' => number_format($tasaImpuesto, 2, '.', ''),
                    'taxable_amount' => number_format($baseLinea, 2, '.', ''),
                ]] : [],
            ];
        }

        return $lineas;
    }

    private function unidadId(object $item): int
    {
        $codigo = (string) ($item->measurementUnit?->code ?? '');

        return self::UNIDADES[$codigo] ?? 70;
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    /** Consecutivo numérico sin el prefijo de la resolución ("SETP980000006" → 980000006). */
    private function consecutivo(CommercialInvoice $factura): int
    {
        $numero = preg_replace('/\D/', '', (string) ($factura->invoice_number ?? ''));

        if ($numero === '' || $numero === null) {
            Log::warning('Factura sin número consecutivo para ApiDian.', ['invoice_uuid' => $factura->uuid]);

            return 0;
        }

        return (int) $numero;
    }
}
