<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CommercialInvoice;
use Illuminate\Support\Facades\Storage;

/**
 * Driver nulo: genera el UBL 2.1 mínimo, lo guarda en disco y no transmite.
 * La factura queda en PENDIENTE_DIAN hasta que se active el proveedor real.
 */
class FacturacionNula implements ContratoFacturacionElectronica
{
    public function emitir(CommercialInvoice $factura): array
    {
        $factura->loadMissing('items', 'customer');

        $ubl = $this->construirUbl($factura);
        $ruta = "ubl/{$factura->company_uuid}/{$factura->invoice_number}.xml";
        Storage::disk('local')->put($ruta, $ubl);

        $factura->withoutLogging(fn () => $factura->update([
            'ubl_document' => $ubl,
            'electronic_status' => 'PENDIENTE_DIAN',
        ]));

        return ['estado' => 'PENDIENTE_DIAN', 'ruta_ubl' => $ruta, 'transmitido' => false];
    }

    public function consultarEstado(CommercialInvoice $factura): array
    {
        return ['estado' => $factura->electronic_status, 'transmitido' => false];
    }

    public function anular(CommercialInvoice $factura, string $motivo): array
    {
        return ['estado' => $factura->electronic_status, 'transmitido' => false, 'motivo' => $motivo];
    }

    private function construirUbl(CommercialInvoice $factura): string
    {
        $items = '';
        foreach ($factura->items as $item) {
            $descripcion = htmlspecialchars((string) $item->description, ENT_XML1);
            $items .= "<cac:InvoiceLine><cbc:ID>{$item->order_index}</cbc:ID>"
                ."<cbc:InvoicedQuantity>{$item->quantity}</cbc:InvoicedQuantity>"
                ."<cbc:LineExtensionAmount>{$item->line_total}</cbc:LineExtensionAmount>"
                ."<cac:Item><cbc:Description>{$descripcion}</cbc:Description></cac:Item>"
                ."<cac:Price><cbc:PriceAmount>{$item->unit_price}</cbc:PriceAmount></cac:Price>"
                .'</cac:InvoiceLine>';
        }

        $numero = htmlspecialchars($factura->invoice_number, ENT_XML1);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            .' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            .' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            ."<cbc:ID>{$numero}</cbc:ID>"
            ."<cbc:IssueDate>{$factura->issue_date->toDateString()}</cbc:IssueDate>"
            ."<cbc:PayableAmount>{$factura->invoice_total}</cbc:PayableAmount>"
            ."{$items}</Invoice>";
    }
}
