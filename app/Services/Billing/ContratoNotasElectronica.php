<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * Puerto de emisión electrónica para notas crédito y débito.
 *
 * Separa la responsabilidad del puerto de facturas
 * (ContratoFacturacionElectronica) porque las notas dependen de la factura
 * ya firmada (billing_reference con su CUFE/CUDE).
 */
interface ContratoNotasElectronica
{
    /** @param CreditNote|DebitNote $nota */
    public function emitirNota(Model $nota): array;

    /** @param CreditNote|DebitNote $nota */
    public function consultarEstadoNota(Model $nota): array;
}
