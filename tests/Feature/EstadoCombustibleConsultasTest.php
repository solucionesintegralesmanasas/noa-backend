<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\Fleet\FuelStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004: el estado de combustible declara su presupuesto de consultas.
 *
 * El flujo recorre los vehículos por lotes de 100 y carga las lecturas del
 * lote en bloque: el conteo es constante dentro del lote (2 vehículos o 90)
 * y crece SOLO por lotes (5 consultas por lote: 1 de vehículos + 4
 * voluminosas), nunca por vehículo. La medición antes/después del módulo
 * único (issue #8) quedó en docs/metrics/fuel-status-20261022-*.json.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class EstadoCombustibleConsultasTest extends TestCase
{
    use InsertaFilas, PresupuestoConsultas, RefreshDatabase;

    private string $empresa;

    private string $prefijoPlacas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Flota presupuesto', 'is_active' => true]);
        $this->prefijoPlacas = 'FE'.substr((string) time(), -5);
    }

    public function test_el_conteo_es_constante_dentro_del_lote(): void
    {
        $this->sembrar(2);

        [$pocos, $muchos] = $this->assertConteoConstante(
            fn () => app(FuelStatusService::class)->estadoDeFlota($this->empresa),
            fn () => $this->sembrar(88), // 90 vehículos: sigue dentro del primer lote
            'el estado de la flota'
        );

        $this->assertSame(5, $pocos);
        $this->assertSame(5, $muchos);
        $this->assertSame(90, count(app(FuelStatusService::class)->estadoDeFlota($this->empresa)['items']));
    }

    public function test_el_conteo_crece_por_lotes_y_no_por_vehiculo(): void
    {
        $this->sembrar(25);

        $pocos = $this->contarConsultas(
            fn () => app(FuelStatusService::class)->estadoDeFlota($this->empresa)
        );
        $this->assertSame(5, $pocos);

        // 205 vehículos en total → 3 lotes: 5 consultas por lote, no por vehículo.
        $this->sembrar(180);
        $muchos = $this->contarConsultas(
            fn () => app(FuelStatusService::class)->estadoDeFlota($this->empresa)
        );

        $this->assertSame($pocos + 10, $muchos, 'El conteo creció por vehículo, no por lote.');
        $this->assertMedicionConDatos(count(app(FuelStatusService::class)->estadoDeFlota($this->empresa)['items']), 'estado de la flota con 205 vehículos');
    }

    public function test_los_avisos_de_agotamiento_siguen_el_mismo_patron_de_lote(): void
    {
        $this->sembrar(2);

        $this->assertConteoConstante(
            fn () => app(FuelStatusService::class)->alertasDeAgotamiento($this->empresa),
            fn () => $this->sembrar(88),
            'el detector de avisos de agotamiento'
        );
    }

    /**
     * Siembra `vehiculos` con tanqueo con odómetros (recorrido dentro del
     * alcance: estado OK), una lectura de preoperacional y planillas con
     * kilometraje final para unas cuantas (las consultas de planillas y
     * proyectos se miden con el mismo conteo tengan o no filas, pero la
     * cordura del tope exige volumen realista).
     */
    private function sembrar(int $vehiculos): void
    {
        $tanqueos = [];
        $inspecciones = [];

        $cargoActual = (int) Vehicle::query()->where('company_uuid', $this->empresa)->count();

        for ($i = 1; $i <= $vehiculos; $i++) {
            $vehiculo = $this->insertar('vehicles', [
                'company_uuid' => $this->empresa,
                'vehicle_license_plate' => sprintf('%s%05d', $this->prefijoPlacas, $cargoActual + $i),
                'fuel_type' => 'DIESEL',
                'engine_displacement' => '3000',
                'is_active' => true,
            ]);

            $tanqueos[] = [
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $vehiculo,
                'date' => '2026-10-10',
                'initial_odometer' => 1000,
                'final_odometer' => 1400,
                'recorrido' => 400,
                'total_spent' => 150000,
                'fuel_price_per_gallon' => 15000,
                'gallons_pumped' => 10,
                'average_performance' => 40,
                'rendimiento' => 15000 / 40,
                'km_per_gallon' => 40,
                'estimated_range_km' => 400,
                'cost_per_km' => 150000 / 400,
                'created_at' => '2026-10-10 10:00:00',
                'updated_at' => '2026-10-10 10:00:00',
            ];

            $inspecciones[] = [
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $vehiculo,
                'inspection_date' => '2026-10-15',
                'mileage' => 1600,
                'created_at' => '2026-10-15 07:00:00',
                'updated_at' => '2026-10-15 07:00:00',
            ];
        }

        foreach (array_chunk($tanqueos, 200) as $parte) {
            \Illuminate\Support\Facades\DB::table('fuel_logs')->insert($parte);
        }

        foreach (array_chunk($inspecciones, 200) as $parte) {
            \Illuminate\Support\Facades\DB::table('vehicle_inspections')->insert($parte);
        }

        foreach (array_slice($tanqueos, 0, 5) as $tanqueo) {
            $planilla = $this->insertar('service_delivery_control_sheet', [
                'company_uuid' => $this->empresa,
                'service_date' => '2026-10-15',
                'ending_kilometer' => '1500',
            ]);
            $this->insertar('service_internal_controls', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $tanqueo['vehicle_uuid'],
                'third_party_uuid' => (string) Str::uuid(),
                'service_delivery_control_sheet_uuid' => $planilla,
            ]);
        }
    }
}
