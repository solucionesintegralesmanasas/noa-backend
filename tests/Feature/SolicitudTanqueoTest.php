<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FuelRequest;
use App\Models\User;
use App\Services\Fleet\FuelLogService;
use App\Services\Fleet\FuelRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Solicitudes de tanqueo: creación, aprobación, QR, uso y vencimiento.
 */
class SolicitudTanqueoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $vehiculo;

    private string $conductor;

    private FuelRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->conductor = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'first_name' => 'JUAN',
            'last_name' => 'PÉREZ',
        ]);

        $proyecto = $this->insertar('projects', [
            'company_uuid' => $this->empresa,
            'project_name' => 'PROYECTO NORTE',
        ]);

        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $proyecto,
            'third_party_uuid' => $this->conductor,
            'vehicle_uuid' => $this->vehiculo,
            'is_active' => true,
        ]);

        // Flujo de llamadas directas al servicio: la empresa activa vive en el
        // contexto de la petición (lo que `SetCompanyContext` resuelve en las
        // peticiones HTTP). Sin ella el servicio falla cerrado desde la
        // corrección de aislamiento.
        request()->attributes->set('current_company_uuid', $this->empresa);

        $this->service = app(FuelRequestService::class);
    }

    public function test_crea_una_solicitud_pendiente_para_el_conductor_asignado(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);

        $this->assertSame('PENDIENTE', $solicitud->status);
        $this->assertStringStartsWith('TQ-', $solicitud->code);
        $this->assertSame($this->conductor, $solicitud->driver_uuid);
        $this->assertNull($solicitud->expires_at);
    }

    public function test_permite_solicitud_sin_conductor_para_usuarios_de_empresa(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, null, null, $this->empresa);

        $this->assertSame('PENDIENTE', $solicitud->status);
        $this->assertNull($solicitud->driver_uuid);
    }

    public function test_no_permite_solicitar_un_vehiculo_no_asignado(): void
    {
        $otro = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'OTRO01',
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->crear($otro, $this->conductor, null, $this->empresa);
    }

    public function test_no_permite_dos_solicitudes_activas_para_el_mismo_vehiculo(): void
    {
        $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);
    }

    public function test_aprueba_y_genera_los_dos_qr_con_los_datos_solicitados(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);

        $aprobada = $this->service->aprobar($solicitud->uuid, (string) Str::uuid(), 'MARÍA GÓMEZ');

        $this->assertSame('APROBADA', $aprobada->status);
        $this->assertNotNull($aprobada->expires_at);
        $this->assertSame('MARÍA GÓMEZ', $aprobada->approved_by_name);
        $this->assertSame('TOYOTA', $aprobada->snapshot['vehiculo']['marca']);
        $this->assertSame('CAMIONETA', $aprobada->snapshot['vehiculo']['clase']);
        $this->assertSame('900123456-7', $aprobada->snapshot['empresa']['nit']);

        $qr = $this->service->qr($aprobada);

        $this->assertStringContainsString('Empresa: TRANSPORTES SIN BARRERAS S.A.S', $qr['data']);
        $this->assertStringContainsString('NIT: 900123456-7', $qr['data']);
        $this->assertStringContainsString('Placa: PUR459', $qr['data']);
        $this->assertStringContainsString('Marca: TOYOTA', $qr['data']);
        $this->assertStringContainsString('Modelo: HILUX 2020', $qr['data']);
        $this->assertStringContainsString('Clase: CAMIONETA', $qr['data']);
        $this->assertStringContainsString('Solicita: JUAN PÉREZ', $qr['data']);
        $this->assertStringContainsString('Aprueba: MARÍA GÓMEZ', $qr['data']);
        $this->assertStringContainsString('/api/v1/public/fuel-requests/', $qr['url']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr['qr_data']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr['qr_url']);
    }

    public function test_no_genera_qr_antes_de_aprobar(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->qr($solicitud);
    }

    public function test_rechaza_con_motivo(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);

        $rechazada = $this->service->rechazar(
            $solicitud->uuid,
            (string) Str::uuid(),
            'MARÍA GÓMEZ',
            'El vehículo tiene el tanque lleno.'
        );

        $this->assertSame('RECHAZADA', $rechazada->status);
        $this->assertSame('El vehículo tiene el tanque lleno.', $rechazada->rejection_reason);
    }

    public function test_expira_una_solicitud_aprobada_despues_de_24_horas(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);
        $this->service->aprobar($solicitud->uuid, (string) Str::uuid(), 'MARÍA GÓMEZ');

        FuelRequest::withoutGlobalScopes()
            ->where('uuid', $solicitud->uuid)
            ->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, $this->service->expirarVencidas($this->empresa));
        $this->assertSame('VENCIDA', FuelRequest::withoutGlobalScopes()->where('uuid', $solicitud->uuid)->value('status'));
    }

    public function test_marca_usada_la_solicitud_al_registrar_el_tanqueo(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);
        $this->service->aprobar($solicitud->uuid, (string) Str::uuid(), 'MARÍA GÓMEZ');

        $log = app(FuelLogService::class)->createFuelLog([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'date' => now()->toDateString(),
            'total_spent' => 150000,
            'fuel_price_per_gallon' => 15000,
            'initial_odometer' => 1000,
            'final_odometer' => 1400,
        ]);

        $usada = FuelRequest::withoutGlobalScopes()->where('uuid', $solicitud->uuid)->first();

        $this->assertSame('USADA', $usada->status);
        $this->assertSame($log->uuid, $usada->fuel_log_uuid);
        $this->assertNotNull($usada->used_at);
    }

    public function test_la_pagina_publica_muestra_los_datos_de_la_solicitud_aprobada(): void
    {
        $solicitud = $this->service->crear($this->vehiculo, $this->conductor, null, $this->empresa);
        $solicitud = $this->service->aprobar($solicitud->uuid, (string) Str::uuid(), 'MARÍA GÓMEZ');

        $this->getJson('/api/v1/public/fuel-requests/'.$solicitud->public_token)
            ->assertOk()
            ->assertJsonPath('data.vehiculo.placa', 'PUR459')
            ->assertJsonPath('data.aprobador', 'MARÍA GÓMEZ')
            ->assertJsonPath('data.valida', true);
    }
}
