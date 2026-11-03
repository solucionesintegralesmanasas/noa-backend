<?php

declare(strict_types=1);

namespace App\Services\Affiliates;

use App\Models\AffiliateDriver;
use App\Models\DriverLicense;
use App\Models\OwnerDriver;
use App\Models\ThirdParty;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Vínculos conductor ↔ afiliado.
 *
 * La pivote es la fuente nueva de visibilidad compartida. Mientras convive con
 * `owners_drivers`, las lecturas incluyen ambos orígenes para que el GPS y los
 * listados actuales no pierdan conductores durante la migración.
 */
class AffiliateDriverService extends BaseService
{
    protected function getModelInstance(): Model
    {
        return new AffiliateDriver;
    }

    /**
     * UUIDs de conductores que puede ver un afiliado: vínculos nuevos + legado.
     *
     * La pivote manda. Si un par existe en `affiliate_drivers` (aunque esté
     * desactivado), el legado no lo vuelve a mostrar: así desvincular a un
     * propietario principal surte efecto y no lo "resucita" `owners_drivers`.
     * El legado solo aporta los pares que la pivote todavía no conoce.
     *
     * @return array<int, string>
     */
    public function driverUuidsForAffiliate(string $affiliateUuid): array
    {
        $registrados = AffiliateDriver::withoutGlobalScopes()
            ->where('affiliate_uuid', $affiliateUuid)
            ->pluck('driver_uuid');

        $activos = AffiliateDriver::withoutGlobalScopes()
            ->where('affiliate_uuid', $affiliateUuid)
            ->where('is_active', true)
            ->pluck('driver_uuid');

        $licencias = OwnerDriver::withoutGlobalScopes()
            ->where('third_party_uuid', $affiliateUuid)
            ->pluck('driver_license_uuid');

        $legado = DriverLicense::withoutGlobalScopes()
            ->whereIn('uuid', $licencias)
            ->pluck('third_party_uuid')
            ->reject(fn (string $driverUuid): bool => $registrados->contains($driverUuid));

        return $activos->merge($legado)->filter()->unique()->values()->all();
    }

    /**
     * Afiliado principal del conductor. Usa la pivote y cae al vínculo legado
     * (licencia activa) solo para los afiliados que la pivote aún no conoce.
     */
    public function primaryAffiliateUuid(string $driverUuid): ?string
    {
        $primario = AffiliateDriver::withoutGlobalScopes()
            ->where('driver_uuid', $driverUuid)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->orderBy('id')
            ->value('affiliate_uuid');

        if ($primario) {
            return $primario;
        }

        $registrados = AffiliateDriver::withoutGlobalScopes()
            ->where('driver_uuid', $driverUuid)
            ->pluck('affiliate_uuid');

        $licencias = DriverLicense::withoutGlobalScopes()
            ->where('third_party_uuid', $driverUuid)
            ->whereIn('status', ['VIGENTE', 'ACTIVA'])
            ->pluck('uuid');

        return OwnerDriver::withoutGlobalScopes()
            ->whereIn('driver_license_uuid', $licencias)
            ->orderByDesc('id')
            ->pluck('third_party_uuid')
            ->reject(fn (string $affiliateUuid): bool => $registrados->contains($affiliateUuid))
            ->first();
    }

    public function isLinked(string $driverUuid, string $affiliateUuid): bool
    {
        return in_array($driverUuid, $this->driverUuidsForAffiliate($affiliateUuid), true);
    }

    /**
     * Vincula un conductor con un afiliado. Es idempotente: si el vínculo ya
     * existe, lo reactiva en vez de duplicarlo.
     */
    public function link(
        string $driverUuid,
        string $affiliateUuid,
        bool $primary = false,
        ?string $createdByUuid = null
    ): AffiliateDriver {
        return $this->transaction(
            fn (): AffiliateDriver => $this->persistirVinculo($driverUuid, $affiliateUuid, $primary, $createdByUuid)
        );
    }

    /**
     * Convierte a `$affiliateUuid` en el propietario principal. El primario
     * anterior se desactiva (traspaso), no se comparte como secundario.
     */
    public function assignPrimary(string $driverUuid, string $affiliateUuid, ?string $createdByUuid = null): AffiliateDriver
    {
        return $this->transaction(function () use ($driverUuid, $affiliateUuid, $createdByUuid): AffiliateDriver {
            AffiliateDriver::withoutGlobalScopes()
                ->where('driver_uuid', $driverUuid)
                ->where('is_primary', true)
                ->where('affiliate_uuid', '!=', $affiliateUuid)
                ->update([
                    'is_primary' => false,
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            return $this->persistirVinculo($driverUuid, $affiliateUuid, true, $createdByUuid);
        });
    }

    /**
     * Desactiva un vínculo sin borrar el histórico. Si era el principal, deja
     * de serlo; la resolución cae al respaldo legado mientras exista.
     */
    public function unlink(string $driverUuid, string $affiliateUuid): bool
    {
        $record = AffiliateDriver::withoutGlobalScopes()
            ->where('driver_uuid', $driverUuid)
            ->where('affiliate_uuid', $affiliateUuid)
            ->first();

        if (! $record) {
            return false;
        }

        $record->update(['is_active' => false, 'is_primary' => false]);

        return true;
    }

    private function persistirVinculo(
        string $driverUuid,
        string $affiliateUuid,
        bool $primary,
        ?string $createdByUuid
    ): AffiliateDriver {
        $driver = ThirdParty::withoutGlobalScopes()->where('uuid', $driverUuid)->first();
        $affiliate = ThirdParty::withoutGlobalScopes()->where('uuid', $affiliateUuid)->first();

        if (! $driver || ! (bool) $driver->is_driver) {
            throw ValidationException::withMessages([
                'driver_uuid' => 'El tercero no está registrado como conductor.',
            ]);
        }

        if (! $affiliate || ! (bool) $affiliate->is_affiliate) {
            throw ValidationException::withMessages([
                'affiliate_uuid' => 'El tercero no está registrado como afiliado.',
            ]);
        }

        if ($driver->company_uuid !== $affiliate->company_uuid) {
            throw ValidationException::withMessages([
                'affiliate_uuid' => 'El conductor y el afiliado deben pertenecer a la misma empresa.',
            ]);
        }

        $record = AffiliateDriver::withoutGlobalScopes()
            ->where('company_uuid', $driver->company_uuid)
            ->where('driver_uuid', $driverUuid)
            ->where('affiliate_uuid', $affiliateUuid)
            ->first();

        $tienePrimario = AffiliateDriver::withoutGlobalScopes()
            ->where('driver_uuid', $driverUuid)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->exists();

        if ($primary) {
            AffiliateDriver::withoutGlobalScopes()
                ->where('driver_uuid', $driverUuid)
                ->where('is_primary', true)
                ->where('affiliate_uuid', '!=', $affiliateUuid)
                ->update([
                    'is_primary' => false,
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }

        if ($record) {
            $record->update([
                'is_active' => true,
                'is_primary' => $primary || $record->is_primary || ! $tienePrimario,
            ]);

            return $record->fresh();
        }

        return AffiliateDriver::create([
            'company_uuid' => $driver->company_uuid,
            'driver_uuid' => $driverUuid,
            'affiliate_uuid' => $affiliateUuid,
            'is_primary' => $primary || ! $tienePrimario,
            'is_active' => true,
            'created_by_uuid' => $createdByUuid ?? Auth::user()?->uuid,
        ]);
    }
}
