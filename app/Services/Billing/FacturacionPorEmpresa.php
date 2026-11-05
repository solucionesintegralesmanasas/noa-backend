<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Resuelve el driver de facturación electrónica por empresa.
 *
 * Driver único: Factus (billing_providers.driver='factus' con credenciales
 * propias). Sin proveedor activo → driver nulo (genera el UBL sin transmitir).
 */
class FacturacionPorEmpresa implements ContratoFacturacionElectronica, ContratoNotasElectronica
{
    public function emitir(CommercialInvoice $factura): array
    {
        return $this->paraDocumento($factura->company_uuid)
            ->emitir($factura);
    }

    public function consultarEstado(CommercialInvoice $factura): array
    {
        return $this->paraDocumento($factura->company_uuid)
            ->consultarEstado($factura);
    }

    public function anular(CommercialInvoice $factura, string $motivo): array
    {
        return $this->paraDocumento($factura->company_uuid)
            ->anular($factura, $motivo);
    }

    public function emitirNota(Model $nota): array
    {
        return $this->paraDocumento($nota->company_uuid)
            ->emitirNota($nota);
    }

    public function consultarEstadoNota(Model $nota): array
    {
        return $this->paraDocumento($nota->company_uuid)
            ->consultarEstadoNota($nota);
    }

    /** Driver activo de la empresa; nulo si no tiene proveedor Factus configurado. */
    private function paraDocumento(?string $companyUuid): ContratoFacturacionElectronica|ContratoNotasElectronica
    {
        $proveedor = $companyUuid ? $this->proveedorDe($companyUuid) : null;

        if ($proveedor?->usaFactus()) {
            return new FactusFacturacion($proveedor);
        }

        return new FacturacionNula;
    }

    private function proveedorDe(string $companyUuid): ?BillingProvider
    {
        return BillingProvider::query()
            ->where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->where('driver', BillingProvider::DRIVER_FACTUS)
            ->orderBy('id')
            ->first();
    }
}
