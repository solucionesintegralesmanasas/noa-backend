<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resuelve qué se exige para una ruta de la API (ver config/authorization.php).
 *
 * Resultado de resolver():
 *  - null                       -> la ruta no se evalúa (exenta o sin permiso mapeable)
 *  - ['tipo' => 'permiso', ...] -> basta con tener UNO de 'permisos'
 *  - ['tipo' => 'rol', ...]     -> hace falta UNO de los 'roles' (catálogos: escritura)
 */
class ResourceAuthorization
{
    private const LECTURA = ['index', 'profile', 'view', 'show'];

    /** @var array<string, true>|null Recursos que tienen permisos creados (p. ej. "vehicles"). */
    private static ?array $recursos = null;

    public static function olvidar(): void
    {
        self::$recursos = null;
    }

    /**
     * @return array{tipo: string, recurso?: string, permisos?: array<int, string>, roles?: array<int, string>}|null
     */
    public function resolver(?string $nombreRuta, string $metodo): ?array
    {
        if (! $nombreRuta || ! str_starts_with($nombreRuta, 'api.v1.')) {
            return null;
        }

        foreach ((array) config('authorization.exempt_prefixes', []) as $prefijo) {
            if ($nombreRuta === $prefijo || str_starts_with($nombreRuta, $prefijo.'.')) {
                return null;
            }
        }

        $partes = explode('.', $nombreRuta);
        array_pop($partes);                // acción
        $segmento = array_pop($partes);    // recurso en kebab-case
        $modulo = $partes[2] ?? null;      // api.v1.<módulo>...
        $metodo = strtoupper($metodo);
        $esLectura = in_array($metodo, ['GET', 'HEAD'], true);

        if ($modulo === 'catalogs') {
            return $esLectura ? null : ['tipo' => 'rol', 'roles' => (array) config('authorization.catalog_write_roles', ['SUPERADMIN'])];
        }

        $recurso = $this->recursoDePermiso((string) $segmento);
        if ($recurso === null) {
            return null;
        }

        $accion = match ($metodo) {
            'POST' => ['create'],
            'PUT', 'PATCH' => ['update'],
            'DELETE' => ['delete'],
            default => self::LECTURA,
        };

        return [
            'tipo' => 'permiso',
            'recurso' => $recurso,
            'permisos' => array_map(fn (string $a) => "{$recurso}.{$a}", $accion),
        ];
    }

    private function recursoDePermiso(string $segmento): ?string
    {
        $overrides = (array) config('authorization.resource_overrides', []);
        if (isset($overrides[$segmento])) {
            return $overrides[$segmento];
        }

        $snake = str_replace('-', '_', $segmento);
        foreach ([$snake, Str::singular($snake), Str::plural($snake)] as $candidato) {
            if (isset(self::recursosConPermisos()[$candidato])) {
                return $candidato;
            }
        }

        return null;
    }

    /** @return array<string, true> */
    private static function recursosConPermisos(): array
    {
        if (self::$recursos === null) {
            self::$recursos = [];
            // Lista de permisos en la caché de Spatie (una lectura de caché, no una consulta por petición).
            foreach (app(PermissionRegistrar::class)->getPermissions()->pluck('name') as $nombre) {
                self::$recursos[explode('.', (string) $nombre)[0]] = true;
            }
        }

        return self::$recursos;
    }
}
