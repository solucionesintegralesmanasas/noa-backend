<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve el driver de facturación electrónica por empresa (persona).
 *
 * Regla acordada: cada empresa elige su proveedor tecnológico y su driver
 * en `billing_providers` (driver='apidian' con credenciales propias);
 * el valor global FACTURACION_DRIVER ya no decide la emisión.
 * Sin proveedor activo → driver nulo (el histórico de fases 1 y 2).
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

    /** Driver activo de la empresa; nulo si no tiene proveedor configurado. */
    private function paraDocumento(?string $companyUuid): ContratoFacturacionElectronica|ContratoNotasElectronica
    {
        $proveedor = $companyUuid ? $this->proveedorDe($companyUuid) : null;

        if ($proveedor?->usaApiDian()) {
            return new ApiDianFacturacion($proveedor);
        }

        if ($proveedor && $proveedor->driver !== BillingProvider::DRIVER_NULO) {
            Log::warning('Facturación electrónica: la empresa tiene un proveedor con driver sin integración', [
                'provider' => $proveedor->uuid,
                'driver' => $proveedor->driver,
            ]);
        }

        return new FacturacionNula;
    }

    private function proveedorDe(string $companyUuid): ?BillingProvider
    {
        return BillingProvider::query()
            ->where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->whereIn('driver', [BillingProvider::DRIVER_APIDIAN, BillingProvider::DRIVER_NULO])
            ->orderBy('id')
            ->first();
    }
}
