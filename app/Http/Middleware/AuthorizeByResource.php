<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\GeneralException;
use App\Support\ResourceAuthorization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización por recurso para todo el grupo `auth:sanctum` (ver config/authorization.php).
 *
 * En modo auditoría (AUTHZ_ENFORCE=false, por defecto) no bloquea: registra un warning por cada
 * acceso que se habría rechazado, para revisar en producción qué usuarios/roles se afectarían.
 */
class AuthorizeByResource
{
    /** @var array<string, bool> */
    private static array $auditoriaEnMemoria = [];

    public function __construct(private readonly ResourceAuthorization $reglas) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ruta = $request->route();
        $usuario = $request->user();

        if (! $ruta || ! $usuario || $this->yaProtegida($ruta->gatherMiddleware())) {
            return $next($request);
        }

        $exigencia = $this->reglas->resolver($ruta->getName(), $request->method());
        if ($exigencia === null || $usuario->hasAnyRole((array) config('authorization.bypass_roles', []))) {
            return $next($request);
        }

        if ($this->cumple($usuario, $exigencia)) {
            return $next($request);
        }

        $detalle = [
            'usuario' => $usuario->uuid ?? $usuario->getKey(),
            'roles' => $usuario->getRoleNames()->all(),
            'ruta' => $ruta->getName(),
            'metodo' => $request->method(),
            'exige' => $exigencia['tipo'] === 'rol' ? $exigencia['roles'] : $exigencia['permisos'],
        ];

        // Los catálogos compartidos se bloquean siempre (salvo AUTHZ_ENFORCE_CATALOGS=false).
        $bloquea = config('authorization.enforce')
            || ($exigencia['tipo'] === 'rol' && config('authorization.enforce_catalogs'));

        if (! $bloquea) {
            // SPEC-014: Sin escrituras en caché en el camino crítico HTTP de auditoría.
            // Deduplicación en memoria para no inundar el log en peticiones repetitivas del mismo endpoint/usuario.
            $clave = sprintf('%s:%s:%s', $detalle['usuario'], $detalle['metodo'], $detalle['ruta']);
            if (! isset(self::$auditoriaEnMemoria[$clave])) {
                if (count(self::$auditoriaEnMemoria) > 500) {
                    self::$auditoriaEnMemoria = [];
                }
                self::$auditoriaEnMemoria[$clave] = true;
                Log::warning('Autorización (auditoría): se habría rechazado este acceso', $detalle);
            }

            return $next($request);
        }

        Log::notice('Autorización: acceso rechazado', $detalle);

        throw GeneralException::forbidden('No tienes permiso para realizar esta acción.');
    }

    /** @param  array<int, mixed>  $middlewares */
    private function yaProtegida(array $middlewares): bool
    {
        foreach ($middlewares as $m) {
            if (is_string($m) && (str_starts_with($m, 'permission:') || str_starts_with($m, 'role:') || str_starts_with($m, 'role_or_permission:'))) {
                return true;
            }
            if (is_string($m) && (str_contains($m, 'PermissionMiddleware') || str_contains($m, 'RoleMiddleware'))) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $exigencia */
    private function cumple(object $usuario, array $exigencia): bool
    {
        if ($exigencia['tipo'] === 'rol') {
            return $usuario->hasAnyRole($exigencia['roles']);
        }

        $tiene = $usuario->getAllPermissions()->pluck('name')->all();

        return count(array_intersect($exigencia['permisos'], $tiene)) > 0;
    }
}
