<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Services\Fleet\FuelLogService;
use App\Services\Fleet\FuelStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Estado de combustible por vehículo: km recorridos desde el último tanqueo,
 * tope (alcance estimado), km restantes y clasificación.
 */
class CombustibleEstadoTest extends TestCase
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
            'vehicle_license_plate' => 'FUEL01',
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function tanquear(array $datos = []): FuelLog
    {
        // 10 galones con odómetros 1.000 → 1.400: 40 km/gal y 400 km de alcance.
        return app(FuelLogService::class)->createFuelLog(array_merge([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'date' => '2026-10-01',
            'total_spent' => 150000,
            'fuel_price_per_gallon' => 15000,
            'initial_odometer' => 1000,
            'final_odometer' => 1400,
        ], $datos));
    }

    private function inspeccion(int $kilometraje, string $fecha = '2026-10-05'): void
    {
        $this->insertar('vehicle_inspections', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'inspection_date' => $fecha,
            'mileage' => $kilometraje,
        ]);
    }

    /** @return array<string, mixed> */
    private function estado(): array
    {
        return app(FuelStatusService::class)->estadoDeFlota($this->empresa);
    }

    public function test_la_ruta_de_estado_esta_registrada(): void
    {
        $this->assertTrue(Route::has('api.v1.fleet.fuel-logs.status'));
    }

    public function test_muestra_los_km_recorridos_y_los_que_faltan(): void
    {
        $this->tanquear();
        $this->inspeccion(1750); // 350 de 400 km

        $item = $this->estado()['items'][0];

        $this->assertSame(350, $item['km_traveled']);
        $this->assertSame(400.0, $item['km_limit']);
        $this->assertSame(50.0, $item['km_remaining']);
        $this->assertSame(87.5, $item['percentage']);
        $this->assertSame('PROXIMO', $item['status']);
        $this->assertSame('tanqueo', $item['baseline']['source']);
        $this->assertSame(8.75, $item['fuel']['gallons_consumed']);
        $this->assertSame(1.25, $item['fuel']['gallons_remaining']);
        $this->assertSame(150000.0, $item['fuel']['money_spent']);
        $this->assertSame(131250.0, $item['fuel']['money_consumed']);
        $this->assertSame(18750.0, $item['fuel']['money_remaining']);
    }

    public function test_marca_excedido_cuando_supera_el_tope(): void
    {
        $this->tanquear();
        $this->inspeccion(1850); // 450 de 400 km

        $item = $this->estado()['items'][0];

        $this->assertSame(450, $item['km_traveled']);
        $this->assertSame(-50.0, $item['km_remaining']);
        $this->assertSame('EXCEDIDO', $item['status']);
        $this->assertStringContainsString('Excedido por 50 km', $item['message']);
        $this->assertSame(11.25, $item['fuel']['gallons_consumed']);
        $this->assertSame(-1.25, $item['fuel']['gallons_remaining']);
        $this->assertSame(168750.0, $item['fuel']['money_consumed']);
        $this->assertSame(-18750.0, $item['fuel']['money_remaining']);
    }

    public function test_muestra_ok_cuando_esta_dentro_del_alcance(): void
    {
        $this->tanquear();
        $this->inspeccion(1600); // 200 de 400 km

        $item = $this->estado()['items'][0];

        $this->assertSame(200, $item['km_traveled']);
        $this->assertSame(200.0, $item['km_remaining']);
        $this->assertSame('OK', $item['status']);
    }

    public function test_usa_el_preoperacional_cercano_si_el_tanqueo_no_tiene_odometro(): void
    {
        $this->tanquear([
            'initial_odometer' => null,
            'final_odometer' => null,
        ]);
        $this->inspeccion(2345, '2026-10-01'); // misma fecha del tanqueo

        $item = $this->estado()['items'][0];

        $this->assertSame('preoperacional', $item['baseline']['source']);
        $this->assertSame(2345, $item['baseline']['odometer']);
        $this->assertSame(0, $item['km_traveled']);
        $this->assertSame('OK', $item['status']);
    }

    public function test_sin_lecturas_de_kilometraje_marca_sin_datos(): void
    {
        $this->tanquear();

        $item = $this->estado()['items'][0];

        $this->assertSame('SIN_DATOS', $item['status']);
        $this->assertNull($item['km_traveled']);
        $this->assertNull($item['current']['odometer']);
        $this->assertNull($item['fuel']['gallons_consumed']);
        $this->assertSame(10.0, $item['fuel']['gallons_pumped']);
        $this->assertNull($item['fuel']['money_consumed']);
        $this->assertSame(150000.0, $item['fuel']['money_spent']);
    }

    public function test_usa_la_planilla_si_su_kilometraje_es_mayor(): void
    {
        $this->tanquear();
        $this->inspeccion(1500);

        $planilla = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $this->empresa,
            'service_date' => '2026-10-05',
            'ending_kilometer' => '1900',
        ]);
        $this->insertar('service_internal_controls', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'third_party_uuid' => (string) Str::uuid(),
            'service_delivery_control_sheet_uuid' => $planilla,
        ]);

        $item = $this->estado()['items'][0];

        $this->assertSame('planilla', $item['current']['source']);
        $this->assertSame(1900, $item['current']['odometer']);
        $this->assertSame(500, $item['km_traveled']);
        $this->assertSame('EXCEDIDO', $item['status']);
    }

    public function test_resume_y_ordena_primero_los_excedidos(): void
    {
        $this->tanquear();
        $this->inspeccion(1850);

        $otro = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'FUEL02',
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => true,
        ]);
        app(FuelLogService::class)->createFuelLog([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $otro,
            'date' => '2026-10-01',
            'total_spent' => 150000,
            'fuel_price_per_gallon' => 15000,
            'initial_odometer' => 1000,
            'final_odometer' => 1400,
        ]);
        $this->insertar('vehicle_inspections', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $otro,
            'inspection_date' => '2026-10-05',
            'mileage' => 1500,
        ]);

        $data = $this->estado();

        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['excedidos']);
        $this->assertSame(1, $data['summary']['ok']);
        $this->assertSame('FUEL01', $data['items'][0]['vehicle_license_plate']);
    }

    public function test_toma_la_primera_inspeccion_posterior_al_tanqueo_como_base(): void
    {
        $tanqueo = $this->tanquear([
            'initial_odometer' => null,
            'final_odometer' => null,
            'average_performance' => 40,
        ]);
        DB::table('fuel_logs')
            ->where('uuid', $tanqueo->uuid)
            ->update(['created_at' => '2026-10-01 10:00:00']);

        $this->insertar('vehicle_inspections', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'inspection_date' => '2026-10-01',
            'mileage' => 2395,
            'created_at' => '2026-10-01 14:00:00',
        ]);
        $this->insertar('vehicle_inspections', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'inspection_date' => '2026-10-01',
            'mileage' => 2440,
            'created_at' => '2026-10-01 15:00:00',
        ]);

        $item = $this->estado()['items'][0];

        $this->assertSame(2395, $item['baseline']['odometer']);
        $this->assertSame(2440, $item['current']['odometer']);
        $this->assertSame(45, $item['km_traveled']);
        $this->assertSame(1.13, $item['fuel']['gallons_consumed']);
    }

    public function test_incluye_los_proyectos_del_vehiculo_para_el_filtro(): void
    {
        $this->tanquear();
        $this->inspeccion(1600);

        $proyecto = $this->insertar('projects', [
            'company_uuid' => $this->empresa,
            'project_name' => 'Proyecto Norte',
        ]);
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $proyecto,
            'third_party_uuid' => (string) Str::uuid(),
            'vehicle_uuid' => $this->vehiculo,
            'is_active' => true,
        ]);

        $data = $this->estado();

        $this->assertSame('Proyecto Norte', $data['items'][0]['projects'][0]['name']);
        $this->assertCount(1, $data['projects']);
        $this->assertSame('Proyecto Norte', $data['projects'][0]['name']);
    }

    public function test_lista_vehiculos_sin_tanqueo_para_ver_toda_la_flota(): void
    {
        $this->tanquear();
        $this->inspeccion(1600);

        $sinTanqueo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'FUEL03',
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => true,
        ]);

        $data = $this->estado();

        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['sin_tanqueo']);

        $item = collect($data['items'])->firstWhere('vehicle_uuid', $sinTanqueo);
        $this->assertNotNull($item);
        $this->assertSame('SIN_TANQUEO', $item['status']);
        $this->assertNull($item['km_traveled']);
        $this->assertNull($item['refuel']);
    }

    public function test_no_mezcla_vehiculos_de_otra_empresa(): void
    {
        $otraEmpresa = $this->insertar('companies', ['business_name' => 'Otra SAS', 'is_active' => true]);
        $otroVehiculo = $this->insertar('vehicles', [
            'company_uuid' => $otraEmpresa,
            'vehicle_license_plate' => 'OTRA01',
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => true,
        ]);
        app(FuelLogService::class)->createFuelLog([
            'company_uuid' => $otraEmpresa,
            'vehicle_uuid' => $otroVehiculo,
            'date' => '2026-10-01',
            'total_spent' => 150000,
            'fuel_price_per_gallon' => 15000,
            'initial_odometer' => 1000,
            'final_odometer' => 1400,
        ]);
        $this->insertar('vehicle_inspections', [
            'company_uuid' => $otraEmpresa,
            'vehicle_uuid' => $otroVehiculo,
            'inspection_date' => '2026-10-05',
            'mileage' => 1850,
        ]);

        $this->tanquear();
        $this->inspeccion(1850);

        $items = $this->estado()['items'];

        $this->assertCount(1, $items);
        $this->assertSame('FUEL01', $items[0]['vehicle_license_plate']);
    }
}
