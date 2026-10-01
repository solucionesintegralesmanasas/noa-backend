<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ARQ-008: los índices compuestos de tracking existen y la migración es reversible.
 * Las cifras que los justifican están en el propio archivo de la migración.
 */
class IndicesTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'database/migrations/2026_10_01_000001_add_composite_indexes_to_tracking_tables.php';

    /** @return array<int, string> columnas del índice, o [] si no existe */
    private function columnas(string $tabla, string $indice): array
    {
        foreach (Schema::getIndexes($tabla) as $i) {
            if ($i['name'] === $indice) {
                return $i['columns'];
            }
        }

        return [];
    }

    public function test_los_indices_compuestos_existen_con_las_columnas_esperadas(): void
    {
        $this->assertSame(['company_uuid', 'recorded_at', 'third_party_uuid'], $this->columnas('driver_locations', 'idx_dl_company_recorded'));
        $this->assertSame(['company_uuid', 'vehicle_uuid', 'recorded_at'], $this->columnas('driver_locations', 'idx_dl_company_vehicle_recorded'));
        $this->assertSame(['company_uuid', 'project_uuid', 'recorded_at'], $this->columnas('driver_locations', 'idx_dl_company_project_recorded'));
        $this->assertSame(['company_uuid', 'is_read', 'created_at'], $this->columnas('driver_location_alerts', 'idx_dla_company_read_created'));
        $this->assertSame(['company_uuid', 'created_at'], $this->columnas('driver_location_alerts', 'idx_dla_company_created'));
        $this->assertSame(['company_uuid', 'service_date'], $this->columnas('service_delivery_control_sheet', 'idx_sdcs_company_date'));
    }

    public function test_la_migracion_se_puede_revertir_y_volver_a_aplicar(): void
    {
        $migracion = require base_path(self::MIGRACION);

        $migracion->down();
        $this->assertSame(['company_uuid', 'recorded_at'], $this->columnas('driver_locations', 'idx_dl_company_recorded'));
        $this->assertSame([], $this->columnas('driver_locations', 'idx_dl_company_vehicle_recorded'));
        $this->assertSame([], $this->columnas('driver_location_alerts', 'idx_dla_company_created'));

        $migracion->up();
        $this->assertSame(['company_uuid', 'recorded_at', 'third_party_uuid'], $this->columnas('driver_locations', 'idx_dl_company_recorded'));
        $this->assertSame(['company_uuid', 'vehicle_uuid', 'recorded_at'], $this->columnas('driver_locations', 'idx_dl_company_vehicle_recorded'));
    }

    public function test_aplicar_dos_veces_la_migracion_no_falla(): void
    {
        $migracion = require base_path(self::MIGRACION);

        $migracion->up(); // idempotente: los índices ya existen

        $this->assertSame(['company_uuid', 'is_read', 'created_at'], $this->columnas('driver_location_alerts', 'idx_dla_company_read_created'));
    }
}
