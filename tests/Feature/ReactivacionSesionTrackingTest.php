<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DriverLocationSession;
use App\Services\Tracking\LocationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Auto-sanación de la sesión de tracking: un vehículo que sigue enviando puntos
 * no puede quedar oculto del mapa porque su sesión se pausó al reiniciar la app.
 */
class ReactivacionSesionTrackingTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $conductor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conductor = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa(),
            'first_name' => 'Conductor',
            'last_name' => 'Prueba',
        ]);
    }

    private function empresa(): string
    {
        return $this->empresa ??= $this->insertar('companies', ['business_name' => 'Empresa Tracking', 'is_active' => true]);
    }

    private function sesion(string $estado, array $extra = []): string
    {
        return $this->insertar('driver_location_sessions', array_merge([
            'company_uuid' => $this->empresa(),
            'third_party_uuid' => $this->conductor,
            'status' => $estado,
            'started_at' => now()->subHour(),
            'total_points' => 3,
            'total_distance_km' => 1.5,
        ], $extra));
    }

    private function punto(array $extra = []): void
    {
        app(LocationTrackingService::class)->storeLocation(array_merge([
            'company_uuid' => $this->empresa(),
            'third_party_uuid' => $this->conductor,
            'latitude' => 4.6100,
            'longitude' => -74.0000,
            'speed' => 25,
            'is_moving' => true,
            'recorded_at' => now()->toDateTimeString(),
        ], $extra));
    }

    public function test_un_punto_reactiva_la_sesion_pausada_del_payload(): void
    {
        $uuid = $this->sesion('paused', ['ended_at' => now()->subMinutes(5)]);

        $this->punto(['session_uuid' => $uuid]);

        $sesion = DriverLocationSession::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('active', $sesion->status);
        $this->assertNull($sesion->ended_at);
        $this->assertSame(4, $sesion->total_points);
    }

    public function test_un_punto_sin_session_uuid_reactiva_la_ultima_pausada_reciente(): void
    {
        $uuid = $this->sesion('paused');

        $this->punto();

        $this->assertSame('active', DriverLocationSession::where('uuid', $uuid)->value('status'));
    }

    public function test_no_se_reactiva_una_sesion_pausada_vieja(): void
    {
        $vieja = $this->sesion('paused', ['updated_at' => now()->subHours(30)]);

        $this->punto();

        $this->assertSame('paused', DriverLocationSession::where('uuid', $vieja)->value('status'));
    }

    public function test_un_punto_de_otro_vehiculo_no_revive_la_sesion_pausada(): void
    {
        $vehiculoA = $this->insertar('vehicles', ['company_uuid' => $this->empresa(), 'vehicle_license_plate' => 'PAU001']);
        $vehiculoB = $this->insertar('vehicles', ['company_uuid' => $this->empresa(), 'vehicle_license_plate' => 'PAU002']);
        $pausada = $this->sesion('paused', ['vehicle_uuid' => $vehiculoA]);

        $this->punto(['vehicle_uuid' => $vehiculoB]);

        $this->assertSame('paused', DriverLocationSession::where('uuid', $pausada)->value('status'));
        $nueva = DriverLocationSession::where('third_party_uuid', $this->conductor)
            ->where('status', 'active')
            ->firstOrFail();
        $this->assertSame($vehiculoB, $nueva->vehicle_uuid);
    }

    public function test_start_session_es_idempotente_con_el_mismo_vehiculo(): void
    {
        $servicio = app(LocationTrackingService::class);
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa(), 'vehicle_license_plate' => 'ABC123']);

        $primera = $servicio->startSession([
            'company_uuid' => $this->empresa(),
            'third_party_uuid' => $this->conductor,
            'vehicle_uuid' => $vehiculo,
        ]);

        $segunda = $servicio->startSession([
            'company_uuid' => $this->empresa(),
            'third_party_uuid' => $this->conductor,
            'vehicle_uuid' => $vehiculo,
        ]);

        $this->assertSame($primera->uuid, $segunda->uuid);
        $this->assertSame(1, DriverLocationSession::where('third_party_uuid', $this->conductor)->where('status', 'active')->count());
    }

    public function test_start_session_reemplaza_la_sesion_si_cambia_el_vehiculo(): void
    {
        $servicio = app(LocationTrackingService::class);
        $primera = $servicio->startSession([
            'company_uuid' => $this->empresa(),
            'third_party_uuid' => $this->conductor,
            'vehicle_uuid' => $this->insertar('vehicles', ['company_uuid' => $this->empresa(), 'vehicle_license_plate' => 'ABC124']),
        ]);
        $segunda = $servicio->startSession([
            'company_uuid' => $this->empresa(),
            'third_party_uuid' => $this->conductor,
            'vehicle_uuid' => $this->insertar('vehicles', ['company_uuid' => $this->empresa(), 'vehicle_license_plate' => 'ABC125']),
        ]);

        $this->assertNotSame($primera->uuid, $segunda->uuid);
        $this->assertSame('paused', $primera->fresh()->status);
        $this->assertSame(1, DriverLocationSession::where('third_party_uuid', $this->conductor)->where('status', 'active')->count());
    }

    public function test_un_punto_no_revive_una_sesion_terminada(): void
    {
        $uuid = $this->sesion('ended', ['ended_at' => now()->subMinute()]);

        $this->punto(['session_uuid' => $uuid]);

        $sesion = DriverLocationSession::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('ended', $sesion->status);
        $this->assertSame(3, $sesion->total_points);
    }

    public function test_el_mapa_oculta_las_sesiones_sin_puntos_recientes(): void
    {
        $this->sesion('active');
        $this->punto();

        // Supera el umbral de frescura (12 h): sale del mapa.
        DB::table('driver_location_sessions')
            ->where('third_party_uuid', $this->conductor)
            ->update(['updated_at' => now()->subHours(13)]);

        $this->assertCount(0, app(LocationTrackingService::class)->getActiveDrivers($this->empresa()));

        // Dentro del umbral (11 h): conserva su última posición conocida.
        DB::table('driver_location_sessions')
            ->where('third_party_uuid', $this->conductor)
            ->update(['updated_at' => now()->subHours(11)]);

        $this->assertCount(1, app(LocationTrackingService::class)->getActiveDrivers($this->empresa()));
    }

    public function test_un_reloj_adelantado_no_guarda_puntos_en_el_futuro(): void
    {
        $this->punto(['recorded_at' => now()->addHour()->toDateTimeString()]);

        $guardado = DB::table('driver_locations')->where('third_party_uuid', $this->conductor)->value('recorded_at');

        $this->assertTrue(Carbon::parse($guardado)->lessThanOrEqualTo(now()));
    }
}
