<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Services\Fleet\FuelLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Alcance estimado (km) a partir de los galones ingresados:
 * galones × rendimiento (km/gal) aplicado.
 */
class CombustibleRendimientoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Combustible SAS', 'is_active' => true]);
        $this->vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function crear(array $datos): FuelLog
    {
        return app(FuelLogService::class)->createFuelLog(array_merge([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'date' => '2026-10-01',
            'total_spent' => 150000,
            'fuel_price_per_gallon' => 15000,
        ], $datos));
    }

    public function test_con_odometros_usa_el_rendimiento_del_registro_y_calcula_el_alcance(): void
    {
        $log = $this->crear([
            'initial_odometer' => 1000,
            'final_odometer' => 1400,
        ]);

        $this->assertSame(10.0, (float) $log->gallons_pumped);
        $this->assertSame(40.0, (float) $log->km_per_gallon);
        $this->assertSame(40.0, (float) $log->average_performance);
        $this->assertSame('registro', $log->performance_source);
        $this->assertSame(400.0, (float) $log->estimated_range_km);
    }

    public function test_sin_odometros_usa_el_promedio_historico(): void
    {
        // Primer registro con odómetros: deja 40 km/gal de histórico.
        $this->crear(['initial_odometer' => 1000, 'final_odometer' => 1400, 'date' => '2026-10-01']);

        // Segundo registro sin odómetros: 20 galones (300000 / 15000).
        $log = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 300000,
        ]);

        $this->assertSame(20.0, (float) $log->gallons_pumped);
        $this->assertSame(40.0, (float) $log->average_performance);
        $this->assertSame('historico', $log->performance_source);
        $this->assertSame(800.0, (float) $log->estimated_range_km);
    }

    public function test_sin_odometros_ni_historico_usa_la_tabla_por_cilindraje(): void
    {
        $log = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 300000, // 20 galones
        ]);

        // Vehículo de prueba: 3000 cc → rango 3.0-3.6 L → 26.5 km/gal.
        $this->assertSame(26.5, (float) $log->average_performance);
        $this->assertSame('cilindraje', $log->performance_source);
        $this->assertSame(530.0, (float) $log->estimated_range_km);
    }

    public function test_sin_cilindraje_ni_historico_no_hay_alcance(): void
    {
        $vehiculoSin = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '',
        ]);

        $log = app(FuelLogService::class)->createFuelLog([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculoSin,
            'date' => '2026-10-05',
            'total_spent' => 300000,
            'fuel_price_per_gallon' => 15000,
        ]);

        $this->assertNull($log->average_performance);
        $this->assertSame('sin_dato', $log->performance_source);
        $this->assertNull($log->estimated_range_km);
    }

    public function test_sin_galones_no_hay_alcance(): void
    {
        $log = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 0,
            'fuel_price_per_gallon' => 0,
        ]);

        $this->assertSame(0.0, (float) $log->gallons_pumped);
        $this->assertNull($log->estimated_range_km);
    }

    public function test_rendimiento_manual_calcula_el_alcance_sin_historico(): void
    {
        $log = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 300000, // 20 galones
            'average_performance' => 35,
        ]);

        $this->assertSame(35.0, (float) $log->average_performance);
        $this->assertSame('manual', $log->performance_source);
        $this->assertSame(700.0, (float) $log->estimated_range_km);
    }

    public function test_limpiar_el_rendimiento_manual_vuelve_a_la_tabla_por_cilindraje(): void
    {
        $log = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 300000,
            'average_performance' => 35,
        ]);
        $this->assertSame('manual', $log->performance_source);

        $actualizado = app(FuelLogService::class)->updateFuelLog($log->uuid, [
            'average_performance' => null,
        ]);

        $this->assertSame(26.5, (float) $actualizado->average_performance);
        $this->assertSame('cilindraje', $actualizado->performance_source);
        $this->assertSame(530.0, (float) $actualizado->estimated_range_km);
    }

    public function test_el_rendimiento_manual_sirve_de_historico_al_siguiente_registro(): void
    {
        $this->crear([
            'date' => '2026-10-01',
            'total_spent' => 300000, // 20 galones
            'average_performance' => 35,
        ]);

        $siguiente = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 150000, // 10 galones
        ]);

        $this->assertSame(35.0, (float) $siguiente->average_performance);
        $this->assertSame('historico', $siguiente->performance_source);
        $this->assertSame(350.0, (float) $siguiente->estimated_range_km);
    }

    public function test_el_consumo_estimado_por_cilindraje_usa_las_horas_de_ralenti(): void
    {
        // Vehículo de prueba: 3000 cc diésel (3.0 L × 0.45 = 1.35 L/h).
        // 3 horas → 4.05 L → 1.07 galones.
        $log = $this->crear([
            'total_spent' => 15000, // 1 galón
            'idle_hours' => 3,
        ]);

        $this->assertSame(3.0, (float) $log->idle_hours);
        $this->assertSame(1.07, (float) $log->gallons_consumed);
        $this->assertSame(-0.07, (float) $log->final_balance);
    }

    public function test_sin_horas_de_ralenti_no_hay_consumo_estimado(): void
    {
        $log = $this->crear([
            'total_spent' => 15000, // 1 galón
            'idle_hours' => 0,
        ]);

        $this->assertSame(0.0, (float) $log->gallons_consumed);
        $this->assertSame(1.0, (float) $log->final_balance);
    }

    public function test_el_alcance_estimado_se_guarda_en_la_base_de_datos(): void
    {
        $log = $this->crear([
            'date' => '2026-10-05',
            'total_spent' => 300000, // 20 galones
            'average_performance' => 35,
        ]);

        $guardado = DB::table('fuel_logs')->where('uuid', $log->uuid)->value('estimated_range_km');
        $this->assertSame(700.0, (float) $guardado);
    }

    public function test_el_rendimiento_y_el_costo_del_kilometraje_se_guardan(): void
    {
        $log = $this->crear([
            'total_spent' => 170000,
            'fuel_price_per_gallon' => 10990,
            'average_performance' => 41.5,
        ]);

        $fila = DB::table('fuel_logs')->where('uuid', $log->uuid)->first();
        $this->assertSame(4096.39, (float) $fila->rendimiento);
        $this->assertSame(264.82, (float) $fila->cost_per_km);
    }
}
