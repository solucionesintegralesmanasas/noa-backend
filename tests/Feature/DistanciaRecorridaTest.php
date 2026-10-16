<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DriverLocation;
use App\Models\DriverLocationSession;
use App\Services\Tracking\LocationHistoryService;
use App\Services\Tracking\LocationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Distancia recorrida por punto: la estadística deja de escanear la tabla.
 *
 * Cada punto guarda la distancia a su anterior y el total del periodo es la
 * suma de esos valores. Un punto que llega desordenado reubica a su vecino,
 * porque la distancia del siguiente deja de ser válida.
 */
class DistanciaRecorridaTest extends TestCase
{
    use RefreshDatabase;

    private string $companyUuid;

    private string $driverUuid;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        $this->companyUuid = (string) Str::uuid();
        $this->driverUuid = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function punto(float $lat, float $lng, string $recordedAt): DriverLocation
    {
        return app(LocationTrackingService::class)->storeLocation([
            'company_uuid' => $this->companyUuid,
            'third_party_uuid' => $this->driverUuid,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => 30,
            'recorded_at' => $recordedAt,
        ]);
    }

    private function distanciaDe(string $uuid): float
    {
        return (float) DriverLocation::where('uuid', $uuid)->value('distance_meters');
    }

    #[Test]
    public function el_primer_punto_no_acumula_distancia(): void
    {
        $punto = $this->punto(4.70, -74.10, '2026-09-28 08:00:00');

        $this->assertSame(0.0, $this->distanciaDe($punto->uuid));
    }

    #[Test]
    public function cada_punto_guarda_la_distancia_a_su_anterior(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $segundo = $this->punto(4.71, -74.10, '2026-09-28 08:10:00');

        // 0,01 grados de latitud son unos 1111,95 m.
        $this->assertEqualsWithDelta(1111.95, $this->distanciaDe($segundo->uuid), 0.5);
    }

    #[Test]
    public function un_punto_desordenado_recalcula_la_distancia_del_siguiente(): void
    {
        $primero = $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $tercero = $this->punto(4.71, -74.10, '2026-09-28 08:10:00');

        // El GPS entrega tarde un punto que en realidad va entre los dos.
        $segundo = $this->punto(4.705, -74.10, '2026-09-28 08:05:00');

        $this->assertEqualsWithDelta(555.97, $this->distanciaDe($segundo->uuid), 0.5);
        // El que era último deja de medir contra el primero.
        $this->assertEqualsWithDelta(555.97, $this->distanciaDe($tercero->uuid), 0.5);
        $this->assertSame(0.0, $this->distanciaDe($primero->uuid));
    }

    #[Test]
    public function las_estadisticas_suman_la_distancia_guardada(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:10:00');

        $stats = app(LocationHistoryService::class)->getDriverStats(
            $this->driverUuid,
            $this->companyUuid,
            '2026-09-28',
            '2026-09-28'
        );

        $this->assertSame(2, $stats['range']['total_points']);
        $this->assertEqualsWithDelta(1.11, $stats['range']['total_distance_km'], 0.01);
    }

    #[Test]
    public function un_rango_sin_puntos_no_inventa_distancia(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');

        $stats = app(LocationHistoryService::class)->getDriverStats(
            $this->driverUuid,
            $this->companyUuid,
            '2026-09-20',
            '2026-09-21'
        );

        $this->assertSame(0, $stats['range']['total_points']);
        $this->assertEqualsWithDelta(0.0, $stats['range']['total_distance_km'], 0.001);
    }

    #[Test]
    public function el_rango_no_cuenta_el_tramo_previo_al_primer_punto(): void
    {
        // A queda fuera del rango. B es el primer punto consultado y guarda la
        // distancia recorrida desde A, que pertenece al día anterior.
        $this->punto(4.70, -74.10, '2026-09-27 23:50:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:00:00');

        $stats = app(LocationHistoryService::class)->getDriverStats(
            $this->driverUuid,
            $this->companyUuid,
            '2026-09-28',
            '2026-09-28'
        );

        $this->assertSame(1, $stats['range']['total_points']);
        $this->assertEqualsWithDelta(0.0, $stats['range']['total_distance_km'], 0.001);
    }

    #[Test]
    public function con_la_misma_marca_de_tiempo_el_anterior_es_el_ultimo_insertado(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $segundo = $this->punto(4.71, -74.10, '2026-09-28 08:00:00');
        $tercero = $this->punto(4.72, -74.10, '2026-09-28 08:00:00');

        // Cada punto se mide contra el anterior real, no contra el primero.
        $this->assertEqualsWithDelta(1111.95, $this->distanciaDe($segundo->uuid), 0.5);
        $this->assertEqualsWithDelta(1111.95, $this->distanciaDe($tercero->uuid), 0.5);
    }

    #[Test]
    public function la_sesion_activa_corrige_el_acumulado_de_un_punto_desordenado(): void
    {
        $servicio = app(LocationTrackingService::class);
        $servicio->startSession([
            'company_uuid' => $this->companyUuid,
            'third_party_uuid' => $this->driverUuid,
        ]);

        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:10:00');

        // Llega tarde un punto intermedio: el tramo del último se acorta y la
        // sesión debe quedar con el recorrido real, no con el viejo.
        $this->punto(4.705, -74.10, '2026-09-28 08:05:00');

        $sesion = DriverLocationSession::where('third_party_uuid', $this->driverUuid)->firstOrFail();

        $this->assertEqualsWithDelta(1.11, (float) $sesion->total_distance_km, 0.01);
        $this->assertSame(3, (int) $sesion->total_points);
    }
}
