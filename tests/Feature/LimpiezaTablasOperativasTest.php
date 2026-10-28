<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\LimpiezaTablasOperativasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Limpieza a cero de las tablas operativas de Hojas de Control de Servicio,
 * Solicitudes de tanqueo (y combustible), Inspecciones Vehiculares y
 * Planillas de control.
 *
 * El seeder debe vaciar sus tablas y sus satélites (firmas, media y registro
 * de actividad) sin tocar lo que es de otros módulos: una firma de vehículo,
 * un archivo de un vehículo, un log ajeno, la flota y el catálogo de ítems de
 * inspección.
 */
class LimpiezaTablasOperativasTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    /** Tablas que el seeder deja en cero (las mismas que su constante TABLAS). */
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

    /** Firmas de los módulos: deben desaparecer. */
    private const FIRMAS_DEL_MODULO = [
        'App\\Models\\ServiceDeliveryControlSheet',
        'App\\Models\\ServiceDeliveryControlSheetRoute',
        'App\\Models\\ServiceDeliveryControlSheetCoordinator',
        'vehicle_inspection',
        'vehicle_inspection_inspector',
        'vehicle_inspection_coordinator',
    ];

    private string $empresa;

    private string $vehiculo;

    private string $rutaMapa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->empresa = $this->insertar('companies', [
            'business_name' => 'TRANSPORTES SIN BARRERAS S.A.S',
            'document_number' => '900123456',
            'verification_digit' => '7',
            'is_active' => true,
        ]);

        $marca = $this->insertar('brands', ['description' => 'TOYOTA']);
        $clase = $this->insertar('vehicle_class', ['description' => 'CAMIONETA']);

        $this->vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'PUR459',
            'brand_uuid' => $marca,
            'vehicle_class_uuid' => $clase,
            'line' => 'HILUX',
            'model' => '2020',
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => true,
        ]);

        $this->sembrarModulos();
        $this->sembrarSatelites();
    }

    /** Rellena las 9 tablas de los módulos con una fila cada una. */
    private function sembrarModulos(): void
    {
        // Catálogo: NO se limpia, pero alimenta el resultado de la inspección.
        $item = $this->insertar('inspection_items', [
            'category' => 'DOCUMENTOS',
            'description' => 'Licencia de tránsito',
        ]);

        $hoja = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $this->empresa,
            'service_date' => '2026-10-01',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
        ]);

        $this->insertar('service_delivery_control_sheet_routes', [
            'service_delivery_control_sheet_uuid' => $hoja,
            'origin' => 'Bodega',
            'destination' => 'Cliente',
        ]);

        $this->insertar('service_internal_controls', [
            'service_delivery_control_sheet_uuid' => $hoja,
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
        ]);

        $this->insertar('service_internal_controls_subcontracted', [
            'service_delivery_control_sheet_uuid' => $hoja,
            'vehicle_class_uuid' => $this->insertar('vehicle_class', ['description' => 'BUS']),
        ]);

        $inspeccion = $this->insertar('vehicle_inspections', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'inspection_date' => '2026-10-01',
            'mileage' => 12000,
        ]);

        // Sin `uuid`: se inserta directo (el auxiliar `insertar` lo añade siempre).
        DB::table('inspection_results')->insert([
            'inspection_uuid' => $inspeccion,
            'item_uuid' => $item,
            'status' => 'APROBADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertar('fuel_requests', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'code' => 'TQ-LIMPIEZA-1',
            'public_token' => 'token-limpieza-1',
            'status' => 'PENDIENTE',
        ]);

        $this->insertar('fuel_logs', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'date' => '2026-10-01',
            'initial_odometer' => 12000,
            'final_odometer' => 12100,
            'recorrido' => 100,
        ]);

        $this->insertar('control_sheets', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'observations' => 'Planilla de control',
        ]);
    }

    /**
     * Columnas comunes de `media`. Las cuatro json no las rellena el auxiliar
     * `insertar`: en MariaDB el tipo `json` es `longtext` con comprobación, así
     * que un `x` rechaza el insert.
     */
    private function filaMedia(array $datos): array
    {
        return $datos + [
            'disk' => 'public',
            'size' => 3,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
        ];
    }

    /** Firmas, media y activity_log de los módulos y de otros, para el contraste. */
    private function sembrarSatelites(): void
    {        $idHoja = $this->idDe('service_delivery_control_sheet');
        $idInspeccion = $this->idDe('vehicle_inspections');
        $idVehiculo = $this->idDe('vehicles');

        foreach ([
            ['App\\Models\\ServiceDeliveryControlSheet', $idHoja, 'signatures/hoja.png'],
            ['vehicle_inspection', $idInspeccion, 'signatures/inspeccion.png'],
            ['vehicle', $idVehiculo, 'signatures/vehiculo.png'],
        ] as [$tipo, $entidad, $ruta]) {
            $this->insertar('signatures', [
                'entity_type' => $tipo,
                'entity_id' => $entidad,
                'company_uuid' => $this->empresa,
                'path' => $ruta,
                'disk' => 'public',
                'status' => 'vigente',
            ]);

            Storage::disk('public')->put($ruta, 'png');
        }

        // Archivo del mapa de recorrido, en la ruta que arma CompanyPathGenerator.
        $mapa = $this->insertar('media', $this->filaMedia([
            'model_type' => 'App\\Models\\ServiceDeliveryControlSheet',
            'model_id' => $idHoja,
            'collection_name' => 'ROUTE_MAP',
            'name' => 'mapa',
            'file_name' => 'mapa.png',
        ]));

        $media = (new Media)->newFromBuilder((array) DB::table('media')->where('uuid', $mapa)->first());
        $this->rutaMapa = app(config('media-library.path_generator'))->getPath($media).$media->file_name;
        Storage::disk('public')->put($this->rutaMapa, 'mapa');

        // Archivo de otro módulo: debe conservarse.
        $this->insertar('media', $this->filaMedia([
            'model_type' => 'App\\Models\\Vehicle',
            'model_id' => $idVehiculo,
            'collection_name' => 'FOTO_VEHICULO',
            'name' => 'foto',
            'file_name' => 'foto.png',
        ]));

        // Sin `uuid`: `activity_log` no lo tiene y `insertar` lo añade siempre.
        foreach (['service_delivery_control_sheet', 'vehicle'] as $log) {
            DB::table('activity_log')->insert([
                'log_name' => $log,
                'description' => "registro {$log}",
                'subject_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                'subject_id' => $idHoja,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function idDe(string $tabla): int
    {
        return (int) DB::table($tabla)->orderByDesc('id')->value('id');
    }

    private function limpiar(): void
    {
        (new LimpiezaTablasOperativasSeeder())->__invoke();
    }

    public function test_las_tablas_de_los_modulos_quedan_en_cero(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertSame(1, DB::table($tabla)->count(), "la tabla {$tabla} debería tener datos antes de limpiar");
        }

        $this->limpiar();

        foreach (self::TABLAS as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "la tabla {$tabla} no quedó en cero");
        }

        $this->assertSame(0, DB::table('signatures')->whereIn('entity_type', self::FIRMAS_DEL_MODULO)->count());
        $this->assertSame(0, DB::table('media')->where('model_type', 'App\\Models\\ServiceDeliveryControlSheet')->count());
        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'service_delivery_control_sheet')->count());
    }

    public function test_conserva_lo_que_no_pertenece_a_los_modulos(): void
    {
        $this->limpiar();

        $this->assertSame(1, DB::table('signatures')->where('entity_type', 'vehicle')->count(), 'la firma de vehículo debe sobrevivir');
        $this->assertSame(1, DB::table('media')->where('model_type', 'App\\Models\\Vehicle')->count(), 'el archivo del vehículo debe sobrevivir');
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'vehicle')->count(), 'el log ajeno debe sobrevivir');
        $this->assertSame(1, DB::table('inspection_items')->count(), 'el catálogo de ítems no se limpia');
        $this->assertSame(1, DB::table('vehicles')->count(), 'la flota no se toca');
    }

    public function test_borra_los_archivos_de_las_firmas_y_del_mapa(): void
    {
        $this->assertTrue(Storage::disk('public')->exists('signatures/hoja.png'));
        $this->assertTrue(Storage::disk('public')->exists($this->rutaMapa));

        $this->limpiar();

        $this->assertFalse(Storage::disk('public')->exists('signatures/hoja.png'), 'el PNG de la firma de la hoja debe borrarse');
        $this->assertFalse(Storage::disk('public')->exists('signatures/inspeccion.png'), 'el PNG de la firma de la inspección debe borrarse');
        $this->assertFalse(Storage::disk('public')->exists($this->rutaMapa), 'el mapa del recorrido debe borrarse');
        $this->assertTrue(Storage::disk('public')->exists('signatures/vehiculo.png'), 'el PNG ajeno se conserva');
    }

    public function test_es_idempotente(): void
    {
        $this->limpiar();
        $this->limpiar();

        foreach (self::TABLAS as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "la tabla {$tabla} no quedó en cero");
        }

        $this->assertSame(1, DB::table('signatures')->where('entity_type', 'vehicle')->count());
    }

    public function test_en_produccion_se_niega_sin_la_confirmacion_explicita(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LIMPIEZA_CONFIRMADA');

        $this->limpiar();
    }
}
