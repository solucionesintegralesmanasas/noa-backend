<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FuelLog;
use App\Models\Notification;
use App\Services\Fleet\FuelLogService;
use App\Services\Notifications\NotificationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Aviso de combustible por kilometraje: el preoperacional diario (o el
 * kilometraje final de la planilla) marca cuánto se ha recorrido desde el
 * último tanqueo y se compara con el alcance estimado del registro.
 */
class CombustibleNotificacionTest extends TestCase
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

    /** @return array<int, array<string, mixed>> */
    private function alertas(): array
    {
        return app(NotificationsService::class)->notificationsForFuelLevel($this->empresa);
    }

    private function inspeccion(int $kilometraje): void
    {
        $this->insertar('vehicle_inspections', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'inspection_date' => '2026-10-05',
            'mileage' => $kilometraje,
        ]);
    }

    public function test_avisa_cuando_se_supera_el_alcance_estimado(): void
    {
        $this->tanquear();
        $this->inspeccion(1850); // 450 km recorridos sobre 400 estimados

        $alertas = $this->alertas();

        $this->assertCount(1, $alertas);
        $this->assertSame('VENCIDO', $alertas[0]['status']);
        $this->assertSame(450, $alertas[0]['km_since_refuel']);
        $this->assertSame(-50, $alertas[0]['km_remaining']);
        $this->assertStringContainsString('superando el alcance estimado', $alertas[0]['message']);
    }

    public function test_avisa_cuando_resta_la_reserva_del_alcance(): void
    {
        $this->tanquear();
        $this->inspeccion(1750); // 350 de 400: restan 50 km (reserva)

        $alertas = $this->alertas();

        $this->assertCount(1, $alertas);
        $this->assertSame('PROXIMO', $alertas[0]['status']);
        $this->assertSame(50, $alertas[0]['km_remaining']);
        $this->assertStringContainsString('Restan 50 km', $alertas[0]['message']);
    }

    public function test_no_avisa_si_el_recorrido_esta_dentro_del_alcance(): void
    {
        $this->tanquear();
        $this->inspeccion(1600); // 200 de 400 km

        $this->assertSame([], $this->alertas());
    }

    public function test_no_avisa_sin_lectura_de_kilometraje(): void
    {
        $this->tanquear();

        $this->assertSame([], $this->alertas());
    }

    public function test_no_avisa_si_el_tanqueo_no_tiene_odometro(): void
    {
        // Alcance calculable por rendimiento manual, pero sin línea de base.
        $this->tanquear([
            'initial_odometer' => null,
            'final_odometer' => null,
            'total_spent' => 300000, // 20 galones
            'average_performance' => 35,
        ]);
        $this->inspeccion(5000);

        $this->assertSame([], $this->alertas());
    }

    public function test_un_tanqueo_nuevo_sin_odometro_no_usa_la_linea_de_base_vieja(): void
    {
        $this->tanquear();
        $this->inspeccion(1850);

        // Tanqueo posterior sin odómetro: el tanque se llenó, pero no se puede
        // saber cuánto se recorrió. No se alerta con la base del tanqueo viejo.
        $this->tanquear([
            'date' => '2026-10-05',
            'initial_odometer' => null,
            'final_odometer' => null,
        ]);

        $this->assertSame([], $this->alertas());
    }

    public function test_usa_el_kilometraje_final_de_la_planilla_si_es_mayor(): void
    {
        $this->tanquear();
        $this->inspeccion(1500); // 100 km

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

        $alertas = $this->alertas();

        $this->assertCount(1, $alertas);
        $this->assertSame(500, $alertas[0]['km_since_refuel']);
        $this->assertSame(-100, $alertas[0]['km_remaining']);
    }

    public function test_el_sync_persiste_el_aviso_y_lo_reinicia_con_un_tanqueo_nuevo(): void
    {
        $this->tanquear();
        $this->inspeccion(1850);

        app(NotificationsService::class)->syncNotifications($this->empresa);

        $query = Notification::query()
            ->where('company_uuid', $this->empresa)
            ->where('type', 'VEHICLE_FUEL_ALERT');

        $this->assertSame(1, $query->count());
        $notificacion = $query->first();
        $this->assertSame(-50, $notificacion->days_left);
        $this->assertSame('VENCIDO', $notificacion->extra_data['status']);
        $this->assertSame('Registrar tanqueo', $notificacion->extra_data['actions'][1]['label']);

        // Volver a sincronizar no duplica ni revivie la alerta leída.
        $notificacion->update(['status' => 'LEIDA']);
        app(NotificationsService::class)->syncNotifications($this->empresa);
        $this->assertSame(1, $query->count());
        $this->assertSame('LEIDA', $query->first()->status);

        // Un tanqueo en el odómetro actual reinicia el control: el aviso desaparece.
        $this->tanquear([
            'date' => '2026-10-05',
            'initial_odometer' => 1850,
            'final_odometer' => 1890,
        ]);
        app(NotificationsService::class)->syncNotifications($this->empresa);

        $this->assertSame(0, $query->count());
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

        $alertas = $this->alertas();

        $this->assertCount(1, $alertas);
        $this->assertSame($this->vehiculo, $alertas[0]['vehicle_uuid']);
    }
}
