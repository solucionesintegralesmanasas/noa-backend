<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DriverLocation;
use App\Models\DriverLocationDailyStat;
use App\Services\Tracking\LocationHistoryService;
use App\Services\Tracking\LocationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Resumen diario del recorrido: el mapa lee las muestras guardadas por día
 * en vez de agrupar la ventana completa.
 *
 * Cada punto mantiene su día al guardarse; un punto tardío ajusta el día que
 * corresponda. Sin resumen de algún día, el mapa usa el cálculo por cubetas.
 */
class ResumenDiarioMapaTest extends TestCase
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

    private function resumenDe(string $fecha): ?DriverLocationDailyStat
    {
        return DriverLocationDailyStat::where('third_party_uuid', $this->driverUuid)
            ->where('service_date', $fecha)
            ->first();
    }

    private function mapa(string $inicio, string $fin, int $maxPoints = 1000): \Illuminate\Support\Collection
    {
        return app(LocationHistoryService::class)->getDriverHistoryForMap(
            $this->driverUuid,
            $inicio,
            $fin,
            $this->companyUuid,
            $maxPoints
        );
    }

    /** @return array<int, string> */
    private function marcas(\Illuminate\Support\Collection $items): array
    {
        return $items->map(fn ($i) => (string) (is_array($i) ? $i['recorded_at'] : $i->recorded_at))->all();
    }

    #[Test]
    public function el_resumen_del_dia_cuenta_puntos_y_distancia(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:10:00');
        $this->punto(4.72, -74.10, '2026-09-28 08:20:00');

        $resumen = $this->resumenDe('2026-09-28');

        $this->assertNotNull($resumen);
        $this->assertSame(3, $resumen->total_points);
        // Dos tramos de 0,01 grados de latitud.
        $this->assertEqualsWithDelta(2223.9, (float) $resumen->total_distance_meters, 1.0);
        $this->assertCount(3, $resumen->samples);
        $this->assertSame(
            ['2026-09-28 08:00:00', '2026-09-28 08:10:00', '2026-09-28 08:20:00'],
            array_column($resumen->samples, 2)
        );
    }

    #[Test]
    public function cada_dia_tiene_su_propia_fila(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:10:00');
        $this->punto(4.72, -74.10, '2026-09-29 08:00:00');

        $hoy = $this->resumenDe('2026-09-28');
        $manana = $this->resumenDe('2026-09-29');

        $this->assertSame(2, $hoy->total_points);
        $this->assertSame(1, $manana->total_points);
        // El único punto del día 29 trae el tramo recorrido desde el día 28.
        $this->assertEqualsWithDelta(1111.95, (float) $manana->total_distance_meters, 0.5);
    }

    #[Test]
    public function el_tope_de_muestras_conserva_primero_y_ultimo(): void
    {
        for ($i = 0; $i < 350; $i++) {
            $minuto = str_pad((string) intdiv($i, 60), 2, '0', STR_PAD_LEFT);
            $segundo = str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT);
            $this->punto(4.70 + $i / 100000, -74.10, "2026-09-28 {$minuto}:{$segundo}:00");
        }

        $resumen = $this->resumenDe('2026-09-28');

        $this->assertSame(350, $resumen->total_points);
        $muestras = $resumen->samples;
        $this->assertCount(DriverLocationDailyStat::MUESTRAS_POR_DIA, $muestras);
        $this->assertSame('2026-09-28 00:00:00', $muestras[0][2]);
        $this->assertSame('2026-09-28 05:49:00', end($muestras)[2]);
    }

    #[Test]
    public function el_mapa_desde_resumenes_respeta_tope_y_orden(): void
    {
        for ($dia = 28; $dia <= 30; $dia++) {
            for ($i = 0; $i < 10; $i++) {
                $this->punto(4.70 + $i / 1000, -74.10, "2026-09-{$dia} 08:" . str_pad((string) ($i * 5), 2, '0', STR_PAD_LEFT) . ":00");
            }
        }

        $trazado = $this->mapa('2026-09-28', '2026-09-30', 100);

        $this->assertCount(30, $trazado);
        $marcas = $this->marcas($trazado);
        $ordenadas = $marcas;
        sort($ordenadas);
        $this->assertSame($ordenadas, $marcas);
        $this->assertSame('2026-09-28 08:00:00', $marcas[0]);
        $this->assertSame('2026-09-30 08:45:00', end($marcas));
    }

    #[Test]
    public function el_mapa_diezma_cuando_supera_el_tope(): void
    {
        for ($i = 0; $i < 250; $i++) {
            $hora = str_pad((string) intdiv($i, 60), 2, '0', STR_PAD_LEFT);
            $minuto = str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT);
            $this->punto(4.70 + $i / 10000, -74.10, "2026-09-28 {$hora}:{$minuto}:00");
        }

        $trazado = $this->mapa('2026-09-28', '2026-09-28', 100);

        $this->assertCount(100, $trazado);
        $marcas = $this->marcas($trazado);
        $this->assertSame('2026-09-28 00:00:00', $marcas[0]);
        $this->assertSame('2026-09-28 04:09:00', end($marcas));
    }

    #[Test]
    public function sin_resumen_el_mapa_usa_el_calculo_por_cubetas(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:10:00');
        $this->punto(4.72, -74.10, '2026-09-28 08:20:00');

        $this->assertNotNull($this->resumenDe('2026-09-28'));

        DriverLocationDailyStat::query()->delete();

        $trazado = $this->mapa('2026-09-28', '2026-09-28');

        // El respaldo cubre el rango aunque no haya resúmenes.
        $this->assertGreaterThan(0, $trazado->count());
        $marcas = $this->marcas($trazado);
        $ordenadas = $marcas;
        sort($ordenadas);
        $this->assertSame($ordenadas, $marcas);
    }

    #[Test]
    public function un_punto_tardio_ajusta_el_resumen_sin_duplicar(): void
    {
        $this->punto(4.70, -74.10, '2026-09-28 08:00:00');
        $this->punto(4.71, -74.10, '2026-09-28 08:10:00');

        // Llega tarde un punto intermedio del mismo día.
        $this->punto(4.705, -74.10, '2026-09-28 08:05:00');

        $resumen = $this->resumenDe('2026-09-28');

        $this->assertSame(3, $resumen->total_points);
        $this->assertEqualsWithDelta(1111.95, (float) $resumen->total_distance_meters, 1.0);
        $this->assertCount(3, $resumen->samples);
    }
}
