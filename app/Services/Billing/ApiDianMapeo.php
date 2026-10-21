<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\ThirdParty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Traduce los documentos internos (facturas y notas) al payload JSON
 * del API de ApiDian (formato "apidian": resolution embebida, notes como array).
 *
 * Los identificadores de catálogo DIAN usan los mismos valores que el sandbox
 * de ApiDian y pueden sobreescribirse por empresa con `billing_providers.settings`
 * (claves: type_document_ids, municipality_id, unit_measure_id, tax_id).
 *
 * Referencia: colección Postman "ApiDian - EMPRESA DE PRUEBAS (Sandbox)".
 */
class ApiDianMapeo
{
    /**
     * IDs de ApiDian según catálogo DIAN (los del sandbox de la colección).
     *
     * @var array<string, int>
     */
    private const DOC_IDENTIFICACION = [
        'CC' => 3,       // Cédula de ciudadanía
        'NIT' => 6,      // NIT (los payloads del sandbox usan 6 con NIT 9 dígitos)
        'CE' => 4,       // Cédula de extranjería
        'REGISTRO_CIVIL' => 1,
        'TARJETA_IDENTIDAD' => 2,
        'PASAPORTE' => 7,
    ];

    public function __construct(
        /** Overrides por empresa leídos de billing_providers.settings. */
        private readonly array $ajustes = []
    ) {}

    private function valor(string $clave, int|string $porDefecto): int|string
    {
        return \Illuminate\Support\Arr::get($this->ajustes, $clave) ?? $porDefecto;
    }

    /** Punto de entrada de una factura de venta interna. */
    public function factura(Model $factura): array
    {
        $factura->loadMissing('items', 'customer', 'resolution');

        $payload = [
            'number' => $this->numeroIncremental($factura->invoice_number),
            'type_document_id' => 1,
            'date' => $factura->issue_date?->toDateString(),
            'time' => $factura->issue_date?->toTimeString(),
            'customer' => $this->cliente($factura->customer),
            'payment_form' => $this->formaPago($factura),
            'allowance_charges' => [],
            'legal_monetary_totals' => [
                'line_extension_amount' => $this->dinero($factura->subtotal),
                'tax_exclusive_amount' => $this->dinero($factura->subtotal),
                'tax_inclusive_amount' => $this->dinero($factura->subtotal + $factura->total_tax),
                'allowance_total_amount' => $this->dinero($factura->total_discount),
                'charge_total_amount' => '0.00',
                'pre_paid_amount' => '0.00',
                'payable_amount' => $this->dinero($factura->invoice_total),
            ],
            'tax_totals' => $this->impuestosFactura($factura),
            'invoice_lines' => $this->lineas($factura->items),
            'send' => null, // La clase emisora define send según provider->send_to_dian
        ];

        // ApiDian resuelve la resolución activa de la cuenta si la clave no
        // viaja; un resolucion_number vacío se envía como cadena y la rechaza.
        if ($factura->resolution?->resolution_number) {
            $payload['resolution_number'] = $factura->resolution->resolution_number;
        }

        if ($factura->notes) {
            $payload['notes'] = [['text' => (string) $factura->notes]];
        }

        return $payload;
    }

    /** Notas crédito y débito (comparten estructura salvo endpoint y tipo). */
    public function nota(Model $nota, string $tipoResolucion, int $discrepancia): array
    {
        $nota->loadMissing('items', 'invoice.customer', 'invoice.resolution');

        $factura = $nota->invoice;

        $payload = [
            'number' => $this->numeroNota($nota->note_number),
            'type_document_id' => (int) $tipoResolucion,
            'date' => $nota->issue_date?->toDateString(),
            'time' => $nota->issue_date?->toTimeString(),
            'billing_reference' => [
                'number' => $factura->invoice_number,
                'uuid' => $factura->cufe ?: '', // CUFE de la factura firmada
                'issue_date' => $factura->issue_date?->toDateString(),
            ],
            'discrepancyresponsecode' => $discrepancia,
            'discrepancyresponsedescription' => $nota->observation ?: 'Ajuste de factura',
            'customer' => $this->cliente($factura->customer),
            'payment_form' => [
                'payment_form' => 1,
                'payment_method_id' => (int) $this->valor('payment_method_id', 10),
            ],
            'legal_monetary_totals' => [
                'line_extension_amount' => $this->dinero($nota->subtotal),
                'tax_exclusive_amount' => $this->dinero($nota->subtotal),
                'tax_inclusive_amount' => $this->dinero($nota->subtotal + $nota->total_tax),
                'allowance_total_amount' => $this->dinero($nota->total_discount),
                'charge_total_amount' => '0.00',
                'pre_paid_amount' => '0.00',
                'payable_amount' => $this->dinero($nota->total),
            ],
            'tax_totals' => $this->impuestosNota($nota),
        ];

        // Igual que en la factura: sin número de resolución, ApiDian toma la activa.
        if ($factura->resolution?->resolution_number) {
            $payload['resolution_number'] = $factura->resolution->resolution_number;
        }

        $claveLineas = $nota instanceof \App\Models\CreditNote ? 'credit_note_lines' : 'debit_note_lines';
        $payload[$claveLineas] = $this->lineas($nota->items);
        $payload['send'] = null;

        return $payload;
    }

    /**
     * Documento genérico (fase 3): el payload llega ya armado; solo se normaliza
     * la bandera de envío, que la clase emisora pone según send_to_dian.
     */
    public function genericos(array $payload): array
    {
        return $payload;
    }

    public function cliente(?ThirdParty $tercero): array
    {
        if (! $tercero) {
            throw new \RuntimeException('El documento no tiene adquiriente asociado.');
        }

        $documento = $this->codigoDocumento($tercero->person_type, $tercero->document_number);

        if ($tercero->person_type === 'JURIDICA') {
            $nombre = $tercero->business_name ?: $tercero->trade_name ?: '';
        } else {
            $nombre = trim(implode(' ', array_filter([$tercero->first_name, $tercero->last_name])));
        }

        return [
            'identification_number' => (int) $tercero->document_number,
            'dv' => $tercero->nit_check_digit !== null ? (int) $tercero->nit_check_digit : null,
            'name' => $nombre,
            'phone' => $tercero->phone,
            'address' => $tercero->address,
            'email' => $tercero->email,
            'merchant_registration' => '000--000',
            'type_document_identification_id' => $documento,
            'type_organization_id' => $tercero->person_type === 'JURIDICA' ? 1 : 2,
            'type_liability_id' => 7,
            'municipality_id' => (int) $this->valor('municipality_id', 149),
            'type_regime_id' => 2,
        ];
    }

    /** @param iterable $items CommercialInvoiceItem|CreditNoteItem|DebitNoteItem */
    public function lineas(iterable $items): array
    {
        $lineas = [];

        foreach ($items as $indice => $item) {
            $lineas[] = [
                'unit_measure_id' => $this->valor('unit_measure_id', '70'),
                'invoiced_quantity' => (string) ($item->quantity ?? 1),
                'line_extension_amount' => $this->dinero($item->line_total),
                'free_of_charge_indicator' => false,
                'description' => Str::limit((string) $item->description, 200),
                'code' => 'ITEM'.str_pad((string) ($item->order_index ?? $indice + 1), 3, '0', STR_PAD_LEFT),
                'type_item_identification_id' => 4,
                'price_amount' => $this->dinero($item->unit_price),
                'base_quantity' => (string) ($item->quantity ?? 1),
                'tax_totals' => (float) ($item->tax_amount ?? 0) > 0 ? [[
                    'tax_id' => (int) $this->valor('tax_id', 1),
                    'tax_amount' => $this->dinero($item->tax_amount),
                    'percent' => rtrim(rtrim(number_format((float) ($item->tax_rate ?? 0), 2, '.', ''), '0'), '.'),
                    'taxable_amount' => $this->dinero($item->line_total),
                ]] : [],
            ];
        }

        return $lineas;
    }

    private function formaPago(Model $factura): array
    {
        $credito = $factura->due_date !== null;

        // ApiDian rechaza claves nulas: en contado se omiten vencimiento y plazo.
        return array_filter([
            'payment_form' => $credito ? 2 : 1,
            'payment_method_id' => (int) $this->valor('payment_method_id', 10), // 10 = efectivo
            'payment_due_date' => $credito ? $factura->due_date->toDateString() : null,
            'duration_measure' => $credito
                ? (int) $factura->issue_date->diffInDays($factura->due_date)
                : null,
        ], static fn ($valor) => $valor !== null);
    }

    private function impuestosFactura(Model $factura): array
    {
        return (float) $factura->total_tax > 0 ? [[
            'tax_id' => (int) $this->valor('tax_id', 1),
            'tax_amount' => $this->dinero($factura->total_tax),
            'percent' => '19.00',
            'taxable_amount' => $this->dinero($factura->subtotal),
        ]] : [];
    }

    private function impuestosNota(Model $nota): array
    {
        return (float) $nota->total_tax > 0 ? [[
            'tax_id' => (int) $this->valor('tax_id', 1),
            'tax_amount' => $this->dinero($nota->total_tax),
            'percent' => '19.00',
            'taxable_amount' => $this->dinero($nota->subtotal),
        ]] : [];
    }

    private function codigoDocumento(?string $tipoPersona, ?string $numero): int
    {
        if ($tipoPersona === 'JURIDICA') {
            return (int) $this->valor('type_document_ids.juridica', self::DOC_IDENTIFICACION['NIT']);
        }

        // Natural: CC por defecto; si no parece cédula (sin 10 dígitos) se deja igual.
        return (int) $this->valor('type_document_ids.natural', self::DOC_IDENTIFICACION['CC']);
    }

    /** "TSB-00000001" o "FE990000001" → 990000001; sin dígitos → 0. */
    private function numeroIncremental(?string $numero): int
    {
        return (int) preg_replace('/\D/', '', (string) $numero);
    }

    /** "NC-0001" → 1. */
    private function numeroNota(?string $numero): int
    {
        return (int) preg_replace('/\D/', '', (string) $numero);
    }

    private function dinero(mixed $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, '.', '');
    }
}
