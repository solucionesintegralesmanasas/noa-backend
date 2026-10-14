<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CommercialInvoice;

/**
 * Puerto de facturación electrónica (proveedor tecnológico).
 *
 * Fase 1 y 2 usan el driver nulo: genera y guarda el UBL sin transmitir.
 * La fase del proveedor solo implementa esta interfaz, sin tocar el resto.
 */
interface ContratoFacturacionElectronica
{
    public function emitir(CommercialInvoice $factura): array;

    public function consultarEstado(CommercialInvoice $factura): array;

    public function anular(CommercialInvoice $factura, string $motivo): array;
}
