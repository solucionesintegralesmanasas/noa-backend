<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\ProjectDriverVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Affiliates\AffiliateDriverService;

/**
 * Qué conductores puede rastrear un AFILIADO: los suyos, no los de otros afiliados de la empresa.
 *
 * Un conductor es "suyo" si está vinculado en `affiliate_drivers` (pivote nueva) o en el legado
 * `owners_drivers` → licencia, o si está asignado en un proyecto a uno de sus vehículos. SUPERADMIN y
 * ADMIN_EMPRESA (o cualquier usuario sin el rol AFILIADO) no tienen restricción y reciben `null`.
 * Un afiliado sin tercero asociado recibe una lista vacía (no ve nada), igual que en
 * `BaseService::aplicarFiltroAfiliado`.
 */
class AlcanceAfiliado
{
    /**
     * @return array{conductores: array<int, string>, vehiculos: array<int, string>}|null  null = sin restricción
     */
    public static function paraUsuario(?User $usuario, ?string $companyUuid): ?array
    {
        if (! $usuario || ! $usuario->hasRole('AFILIADO') || $usuario->hasAnyRole(['SUPERADMIN', 'ADMIN_EMPRESA'])) {
            return null;
        }

        $tercero = $companyUuid
            ? $usuario->companies()->where('company_user.company_uuid', $companyUuid)->first()?->pivot?->third_party_uuid
            : null;

        if (! $tercero) {
            return ['conductores' => [], 'vehiculos' => []];
        }

        $vehiculos = Vehicle::withoutGlobalScopes()
            ->where(function ($q) use ($tercero) {
                $q->where('third_party_uuid', $tercero)
                    ->orWhereHas('owners', fn ($o) => $o->where('third_party_uuid', $tercero));
            })
            ->pluck('uuid')
            ->all();

        // Lectura dual: pivote nueva + legado de owners_drivers, para que el mapa
        // no pierda conductores mientras se completa la migración.
        $vinculados = app(AffiliateDriverService::class)->driverUuidsForAffiliate($tercero);
        $asignados = ProjectDriverVehicle::withoutGlobalScopes()->whereIn('vehicle_uuid', $vehiculos)->pluck('third_party_uuid');

        return [
            'conductores' => collect($vinculados)->merge($asignados)->filter()->unique()->values()->all(),
            'vehiculos' => $vehiculos,
        ];
    }

    /** ¿Puede este usuario ver el rastreo del conductor? (siempre true si no es un afiliado). */
    public static function permiteConductor(?User $usuario, ?string $companyUuid, string $conductorUuid): bool
    {
        $alcance = self::paraUsuario($usuario, $companyUuid);

        return $alcance === null || in_array($conductorUuid, $alcance['conductores'], true);
    }
}
