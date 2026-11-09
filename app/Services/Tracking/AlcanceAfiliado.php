<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\User;
use App\Services\ActorScope\AlcanceDelActor;

/**
 * Qué conductores puede rastrear un AFILIADO: los suyos, no los de otros afiliados de la empresa.
 *
 * SPEC-013: Delega en el servicio centralizado AlcanceDelActor para unificación de reglas
 * y memorización de asignaciones en el ciclo de vida de la petición.
 */
class AlcanceAfiliado
{
    /**
     * @return array{conductores: array<int, string>, vehiculos: array<int, string>}|null  null = sin restricción
     */
    public static function paraUsuario(?User $usuario, ?string $companyUuid): ?array
    {
        $servicio = app(AlcanceDelActor::class);
        if ($usuario !== null || $companyUuid !== null) {
            $servicio->resolverContexto($usuario, $companyUuid);
        }

        return $servicio->obtenerAlcanceRastreo();
    }

    /** ¿Puede este usuario ver el rastreo del conductor? (siempre true si no es un afiliado). */
    public static function permiteConductor(?User $usuario, ?string $companyUuid, string $conductorUuid): bool
    {
        $servicio = app(AlcanceDelActor::class);
        if ($usuario !== null || $companyUuid !== null) {
            $servicio->resolverContexto($usuario, $companyUuid);
        }

        return $servicio->permiteConductor($conductorUuid);
    }
}
