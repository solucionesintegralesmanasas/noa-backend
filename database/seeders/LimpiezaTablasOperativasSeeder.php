<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Filesystem as MediaFilesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deja en cero las tablas operativas de cuatro módulos.
 *
 * Módulos: Hojas de Control de Servicio (PCP), Solicitudes de tanqueo (y su
 * histórico de combustible), Inspecciones Vehiculares y Planillas de control.
 * Además limpia sus tablas satélite, que si no quedarían con datos huérfanos:
 * firmas (con su PNG), archivos de media y el registro de actividad.
 *
 * NO está registrado en DatabaseSeeder: se ejecuta a mano con
 *   php artisan db:seed --class=LimpiezaTablasOperativasSeeder
 * Es idempotente (una segunda corrida borra cero filas) y no toca catálogos:
 * `inspection_items` se conserva tal cual.
 *
 * Orden de borrado: archivos y satélite primero, hijas antes que madres
 * (hay FK con cascade hacia `service_delivery_control_sheet` y desde
 * `inspection_results` hacia `vehicle_inspections`). Todo va en una transacción;
 * los binarios (PNG de firmas y media) se eliminan después del commit para no
 * dejar filas apuntando a archivos ya borrados si algo falla.
 *
 * Reinicia los AUTO_INCREMENT de las tablas vaciadas, salvo que se esté dentro
 * de una transacción (las pruebas con RefreshDatabase): el DDL haría commit
 * implícito y ensuciaría la base `noa_test`.
 *
 * Efectos colaterales de vaciar los datos (a propósito, es una reinicialización):
 * - `vehicle_inspections.mileage` es la línea de base del rendimiento de
 *   combustible: el estado de combustible y las alertas arrancan en SIN_DATOS.
 * - Con `fuec_require_daily_inspections` activa, crear un FUEC exige una
 *   inspección del día.
 * - Con `fuel_logs` vacío no hay histórico de tanqueo ni kilometraje.
 * - Las firmas y el mapa de recorrido borrados son evidencia irreversible.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 */
class LimpiezaTablasOperativasSeeder extends Seeder
{
    /**
     * `signatures.entity_type` de los módulos limpiados (incluye el legado
     * `vehicle_inspection`, sin rol, y las firmas ya reemplazadas o revocadas).
     */
    private const ENTIDADES_FIRMA = [
        'App\\Models\\ServiceDeliveryControlSheet',
        'App\\Models\\ServiceDeliveryControlSheetRoute',
        'App\\Models\\ServiceDeliveryControlSheetCoordinator',
        'vehicle_inspection',
        'vehicle_inspection_inspector',
        'vehicle_inspection_coordinator',
    ];

    /** `media.model_type` de los módulos limpiados. */
    private const MODELOS_MEDIA = [
        'App\\Models\\ServiceDeliveryControlSheet',
        'App\\Models\\ControlSheet',
    ];

    /** `activity_log.log_name` que genera LogsActivity::resolveLogName(). */
    private const LOGS = [
        'service_delivery_control_sheet',
        'control_sheet',
        'fuel_request',
        'fuel_log',
        'vehicle_inspection',
    ];

    /**
     * Tablas a dejar en cero, en orden de borrado: hijas antes que madres y
     * `fuel_requests` antes que `fuel_logs` (la solicitud apunta al registro).
     */
    private const TABLAS = [
        'service_delivery_control_sheet_routes',
        'service_internal_controls',
        'service_internal_controls_subcontracted',
        'inspection_results',
        'service_delivery_control_sheet',
        'vehicle_inspections',
        'fuel_requests',
        'control_sheets',
        'fuel_logs',
    ];

    public function run(): void
    {
        $this->asegurarConfirmado();

        $origen = $this->contarFilas();

        $archivosFirma = [];
        $archivosFallidos = 0;

        DB::transaction(function () use (&$archivosFirma, &$archivosFallidos): void {
            // 1. Media: los archivos salen ANTES de borrar las filas dueñas,
            //    porque CompanyPathGenerator resuelve el NIT del dueño para
            //    armar la ruta (con la hoja ya borrada apuntaría a `fallback/`).
            $archivosFallidos += $this->vaciarMedia();

            // 2. Firmas: aquí solo se apuntan las rutas; el PNG se borra después
            //    del commit. `signatures` usa SoftDeletes y el scope de empresa,
            //    así que se va por query builder para cubrir todas las cuentas
            //    y también las filas ya borradas lógicamente.
            $archivosFirma = $this->vaciarFirmas();

            // 3. Registro de actividad de los módulos.
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->whereIn('log_name', self::LOGS)->delete();
            }

            // 4. Tablas del módulo, hijas primero.
            foreach (self::TABLAS as $tabla) {
                if (Schema::hasTable($tabla)) {
                    DB::table($tabla)->delete();
                }
            }
        });

        $firmasBorradas = $this->borrarArchivosDeFirma($archivosFirma);

        $this->reiniciarAutoIncremental();

        $this->reportar($origen, count($archivosFirma), $firmasBorradas, $archivosFallidos);
    }

    /**
     * En producción exige una confirmación explícita: el seeder borra evidencia
     * firmada de todas las empresas y no se puede deshacer.
     *
     * Se comprueban las tres fuentes porque con `php artisan config:cache` el
     * `.env` no se vuelve a leer y `env()` devuelve null.
     */
    private function asegurarConfirmado(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $valor = env('LIMPIEZA_CONFIRMADA')
            ?? $_SERVER['LIMPIEZA_CONFIRMADA']
            ?? $_ENV['LIMPIEZA_CONFIRMADA']
            ?? null;

        if ((string) $valor === '1') {
            return;
        }

        throw new \RuntimeException(
            'Limpieza bloqueada: define LIMPIEZA_CONFIRMADA=1 (entorno, .env o variables del proceso) para vaciar estas tablas en producción.'
        );
    }

    /** Filas que se van a borrar, para el reporte. */
    private function contarFilas(): array
    {
        $conteos = [];

        foreach (self::TABLAS as $tabla) {
            $conteos[$tabla] = Schema::hasTable($tabla) ? DB::table($tabla)->count() : null;
        }

        if (Schema::hasTable('signatures')) {
            $conteos['signatures'] = DB::table('signatures')->whereIn('entity_type', self::ENTIDADES_FIRMA)->count();
        }

        if (Schema::hasTable('media')) {
            $conteos['media'] = DB::table('media')->whereIn('model_type', self::MODELOS_MEDIA)->count();
        }

        if (Schema::hasTable('activity_log')) {
            $conteos['activity_log'] = DB::table('activity_log')->whereIn('log_name', self::LOGS)->count();
        }

        return $conteos;
    }

    /**
     * Borra los archivos de media de los módulos y sus filas.
     *
     * `Media::delete()` en esta versión de Spatie NO elimina el binario: hay que
     * pedirlo con `Filesystem::removeAllFiles()`. Un archivo que no se pueda
     * borrar no aborta la limpieza: se cuenta y se reporta.
     */
    private function vaciarMedia(): int
    {
        if (! Schema::hasTable('media')) {
            return 0;
        }

        $filesystem = app(MediaFilesystem::class);
        $fallas = 0;

        DB::table('media')->whereIn('model_type', self::MODELOS_MEDIA)
            ->orderBy('id')
            ->chunk(200, function ($filas) use ($filesystem, &$fallas): void {
                $ids = [];

                foreach ($filas as $fila) {
                    $ids[] = $fila->id;

                    try {
                        // Se hidrata desde la fila leída: una consulta por archivo
                        // sería innecesaria aquí (son imágenes por hoja).
                        $media = (new Media)->newFromBuilder((array) $fila);
                        $filesystem->removeAllFiles($media);
                    } catch (\Throwable $e) {
                        $fallas++;
                        report($e);
                    }
                }

                DB::table('media')->whereIn('id', $ids)->delete();
            });

        return $fallas;
    }

    /**
     * Vacía las firmas de los módulos y devuelve sus archivos a borrar.
     *
     * @return array<int, array{disco: string, ruta: string}>
     */
    private function vaciarFirmas(): array
    {
        if (! Schema::hasTable('signatures')) {
            return [];
        }

        $archivos = DB::table('signatures')
            ->whereIn('entity_type', self::ENTIDADES_FIRMA)
            ->get(['id', 'disk', 'path'])
            ->map(fn ($fila) => [
                'disco' => (string) ($fila->disk ?: 'public'),
                'ruta' => (string) $fila->path,
            ])
            ->all();

        DB::table('signatures')->whereIn('entity_type', self::ENTIDADES_FIRMA)->delete();

        return $archivos;
    }

    /** Borra los PNG de las firmas ya retiradas. Devuelve cuántos se quitaron. */
    private function borrarArchivosDeFirma(array $archivos): int
    {
        $borrados = 0;

        foreach ($archivos as $archivo) {
            if ($archivo['ruta'] === '') {
                continue;
            }

            try {
                $disco = Storage::disk($archivo['disco']);

                if ($disco->exists($archivo['ruta'])) {
                    $disco->delete($archivo['ruta']);
                    $borrados++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $borrados;
    }

    /**
     * `ALTER TABLE` provoca commit implícito: dentro de una transacción (las
     * pruebas con RefreshDatabase) se omite para no ensuciar la base `noa_test`.
     */
    private function reiniciarAutoIncremental(): void
    {
        if (DB::transactionLevel() > 0) {
            $this->command?->warn('AUTO_INCREMENT no se reinicia dentro de una transacción.');

            return;
        }

        foreach (self::TABLAS as $tabla) {
            if (Schema::hasTable($tabla)) {
                DB::statement("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
            }
        }
    }

    private function reportar(array $origen, int $firmas, int $firmasBorradas, int $mediaFallidos): void
    {
        $this->command?->info('Limpieza de tablas operativas:');

        foreach ($origen as $tabla => $filas) {
            $this->command?->line(sprintf('  %-42s %s', $tabla, $filas === null ? 'no existe' : $filas));
        }

        $this->command?->line("  PNG de firmas borrados: {$firmasBorradas} de {$firmas}");

        if ($mediaFallidos > 0) {
            $this->command?->warn("Archivos de media que no se pudieron borrar: {$mediaFallidos} (quedan en disco)");
        }
    }
}
