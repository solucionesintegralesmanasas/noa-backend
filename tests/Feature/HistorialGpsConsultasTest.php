<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tracking\LocationHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004: el historial y las estadísticas GPS declaran su presupuesto.
 * El rango y la paginación acotan el trabajo: más puntos no son más consultas.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class HistorialGpsConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    private string $conductor;

    private string $vehiculo;

    private string $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa historial', 'is_active' => true]);
        $this->conductor = $this->insertar('third_parties', ['company_uuid' => $this->empresa, 'first_name' => 'Conductor', 'last_name' => 'GPS']);
        $this->vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa, 'vehicle_license_plate' => 'HIS001', 'is_active' => true]);
        $this->proyecto = $this->insertar('projects', ['company_uuid' => $this->empresa, 'project_name' => 'Proyecto HIS']);
        $this->insertar('driver_location_sessions', [
            'company_uuid' => $this->empresa, 'third_party_uuid' => $this->conductor,
            'vehicle_uuid' => $this->vehiculo, 'project_uuid' => $this->proyecto,
            'status' => 'active', 'started_at' => '2026-09-29 08:00:00',
        ]);
    }

    /** Siembra $n puntos del 2026-09-29 en una sola tanda de inserciones. */
    private function sembrarPuntos(int $n): void
    {
        $filas = [];
        for ($i = 0; $i < $n; $i++) {
            $filas[] = [
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $this->empresa,
                'third_party_uuid' => $this->conductor,
                'vehicle_uuid' => $this->vehiculo,
                'project_uuid' => $this->proyecto,
                'latitude' => 4.6 + $i * 0.00001,
                'longitude' => -74.0 + $i * 0.00001,
                'speed' => 30,
                'recorded_at' => '2026-09-29 08:00:'.str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($filas, 200) as $tanda) {
            DB::table('driver_locations')->insert($tanda);
        }
    }

    public function test_el_historial_no_crece_con_los_puntos_del_rango(): void
    {
        $this->sembrarPuntos(20);
        $medir = fn () => app(LocationHistoryService::class)->getDriverHistory($this->conductor, '2026-09-29', '2026-09-30', $this->empresa, 50, 1);
        $ampliar = fn () => $this->sembrarPuntos(480);

        $this->assertConteoConstante($medir, $ampliar, 'Historial del conductor');
        $this->assertPresupuesto(2, $medir, 'Historial del conductor'); // medido: 2 (conteo + página)
    }

    public function test_las_estadisticas_no_crecen_con_los_puntos_del_rango(): void
    {
        $this->sembrarPuntos(20);
        $medir = fn () => app(LocationHistoryService::class)->getDriverStats($this->conductor, $this->empresa, '2026-09-29', '2026-09-30');
        $ampliar = fn () => $this->sembrarPuntos(480);

        $this->assertConteoConstante($medir, $ampliar, 'Estadísticas del conductor');
        $this->assertPresupuesto(4, $medir, 'Estadísticas del conductor'); // medido: 4 (agregados + sesiones)
    }

    public function test_el_mapa_no_crece_con_los_puntos_del_rango(): void
    {
        $this->sembrarPuntos(20);
        $medir = fn () => app(LocationHistoryService::class)->getDriverHistoryForMap($this->conductor, '2026-09-29', '2026-09-30', $this->empresa);
        $ampliar = fn () => $this->sembrarPuntos(480);

        $this->assertConteoConstante($medir, $ampliar, 'Mapa del conductor');
        $this->assertPresupuesto(3, $medir, 'Mapa del conductor'); // medido: 3 (resúmenes o cubetas)
    }
}
