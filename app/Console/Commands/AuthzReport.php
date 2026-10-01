<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ResourceAuthorization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * Muestra qué exige el middleware `authz` en cada ruta de la API y cuáles quedan sin evaluar.
 */
class AuthzReport extends Command
{
    protected $signature = 'authz:report {--sin-mapa : Solo las rutas autenticadas que no se evalúan} {--json}';

    protected $description = 'Informe de la autorización por recurso (permiso exigido por ruta)';

    public function handle(ResourceAuthorization $reglas): int
    {
        $filas = [];
        foreach (Route::getRoutes() as $ruta) {
            $mw = $ruta->gatherMiddleware();
            if (! str_starts_with($ruta->uri(), 'api/v1') || ! in_array('authz', $mw, true)) {
                continue;
            }
            $metodo = $ruta->methods()[0];
            $exige = $reglas->resolver($ruta->getName(), $metodo);
            $protegida = collect($mw)->contains(fn ($m) => is_string($m) && (str_contains($m, 'permission:') || str_contains($m, 'role:') || str_contains($m, 'PermissionMiddleware') || str_contains($m, 'RoleMiddleware')));
            $estado = $protegida ? 'propia' : ($exige === null ? 'SIN EVALUAR' : ($exige['tipo'] === 'rol' ? 'rol: '.implode('|', $exige['roles']) : implode(' | ', $exige['permisos'])));
            $filas[] = [$metodo, $ruta->uri(), $estado];
        }

        $sinMapa = array_values(array_filter($filas, fn ($f) => $f[2] === 'SIN EVALUAR'));
        $this->line(sprintf('Rutas con authz: %d | evaluadas: %d | sin evaluar: %d | modo: %s',
            count($filas), count($filas) - count($sinMapa), count($sinMapa), config('authorization.enforce') ? 'BLOQUEA' : 'auditoría'));

        $mostrar = $this->option('sin-mapa') ? $sinMapa : $filas;
        if ($this->option('json')) {
            $this->line(json_encode($mostrar, JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Método', 'URI', 'Exige'], $mostrar);
        }

        return self::SUCCESS;
    }
}
