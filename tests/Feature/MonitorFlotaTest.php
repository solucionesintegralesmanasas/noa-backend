<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tracking\LocationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * ARQ-007: el monitor de flota (conductores con sesión GPS activa) adjunta a cada ubicación la
 * planilla del día. Estas pruebas fijan el comportamiento observable y que el número de consultas
 * NO crezca con la cantidad de conductores (antes: 11 + P + 4F).
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class MonitorFlotaTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private string $empresa;

    private function empresa(): string
    {
        return $this->empresa ??= $this->insertar('companies', ['business_name' => 'Empresa de prueba', 'is_active' => true]);
    }

    /**
     * Un conductor con sesión activa y última ubicación, con su vehículo y proyecto.
     *
     * @return array{conductor: string, vehiculo: string, proyecto: string}
     */
    private function conductor(string $placa, ?string $proyecto = null): array
    {
        $empresa = $this->empresa();
        $conductor = (string) Str::uuid();
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $empresa, 'vehicle_license_plate' => $placa]);
        $proyecto ??= $this->insertar('projects', ['company_uuid' => $empresa, 'project_name' => 'Proyecto '.$placa]);

        $this->insertar('driver_location_sessions', [
            'company_uuid' => $empresa, 'third_party_uuid' => $conductor, 'vehicle_uuid' => $vehiculo,
            'project_uuid' => $proyecto, 'status' => 'active', 'started_at' => '2026-09-30 08:00:00',
        ]);
        $this->insertar('driver_locations', [
            'company_uuid' => $empresa, 'third_party_uuid' => $conductor, 'vehicle_uuid' => $vehiculo,
            'project_uuid' => $proyecto, 'latitude' => 4.6, 'longitude' => -74.0, 'recorded_at' => '2026-09-30 09:55:00',
        ]);

        return ['conductor' => $conductor, 'vehiculo' => $vehiculo, 'proyecto' => $proyecto];
    }

    /** Planilla de un vehículo propio (control interno) con $rutas rutas activas. */
    private function planilla(string $vehiculo, string $fecha, int $rutas, ?string $proyecto = null, string $tipo = 'DIRECTO_CON_LA_EMPRESA'): string
    {
        $planilla = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $this->empresa(), 'service_date' => $fecha, 'type_of_control_sheet' => $tipo,
            'project_uuid' => $proyecto, 'is_active' => true,
        ]);
        $this->insertar('service_internal_controls', [
            'company_uuid' => $this->empresa(), 'vehicle_uuid' => $vehiculo, 'service_delivery_control_sheet_uuid' => $planilla,
        ]);
        for ($i = 1; $i <= $rutas; $i++) {
            $this->insertar('service_delivery_control_sheet_routes', [
                'service_delivery_control_sheet_uuid' => $planilla, 'order_index' => $i,
                'origin' => "Origen $i", 'destination' => "Destino $i", 'funcionario_nombre' => "Funcionario $i", 'is_active' => true,
            ]);
        }

        return $planilla;
    }

    /** @return array<string, mixed>|null */
    private function planillaDelMonitor(string $conductor): ?array
    {
        $ubicacion = app(LocationTrackingService::class)->getActiveDrivers($this->empresa())
            ->firstWhere('third_party_uuid', $conductor);

        return $ubicacion?->planilla_dia;
    }

    public function test_adjunta_la_planilla_de_hoy_del_vehiculo_con_sus_rutas(): void
    {
        $d = $this->conductor('AAA111');
        $planilla = $this->planilla($d['vehiculo'], '2026-09-30', 2, $d['proyecto']);

        $r = $this->planillaDelMonitor($d['conductor']);

        $this->assertSame($planilla, $r['uuid']);
        $this->assertTrue($r['es_planilla_hoy']);
        $this->assertSame(['Destino 1', 'Destino 2'], array_column($r['routes']->all(), 'destination'));
        $this->assertSame('Proyecto AAA111', $r['project_name']);
    }

    public function test_sin_planilla_de_hoy_usa_la_ultima_conocida_con_rutas(): void
    {
        $d = $this->conductor('BBB222');
        $anterior = $this->planilla($d['vehiculo'], '2026-09-27', 1, $d['proyecto']);

        $r = $this->planillaDelMonitor($d['conductor']);

        $this->assertSame($anterior, $r['uuid']);
        $this->assertFalse($r['es_planilla_hoy']);
    }

    public function test_si_la_planilla_de_hoy_no_trae_rutas_se_respalda_con_la_ultima_conocida(): void
    {
        $d = $this->conductor('CCC333');
        $this->planilla($d['vehiculo'], '2026-09-30', 0, $d['proyecto']); // disponibilidad, sin rutas
        $anterior = $this->planilla($d['vehiculo'], '2026-09-28', 3, $d['proyecto']);

        $r = $this->planillaDelMonitor($d['conductor']);

        $this->assertSame($anterior, $r['uuid']);
        $this->assertFalse($r['es_planilla_hoy']);
        $this->assertCount(3, $r['routes']);
    }

    public function test_sin_ninguna_planilla_el_campo_queda_en_nulo(): void
    {
        $d = $this->conductor('DDD444');

        $this->assertNull($this->planillaDelMonitor($d['conductor']));
    }

    public function test_prefiere_la_planilla_del_proyecto_del_gps(): void
    {
        $d = $this->conductor('EEE555');
        $otroProyecto = $this->insertar('projects', ['company_uuid' => $this->empresa(), 'project_name' => 'Otro']);
        $this->planilla($d['vehiculo'], '2026-09-30', 1, $otroProyecto);
        $delGps = $this->planilla($d['vehiculo'], '2026-09-30', 1, $d['proyecto']);

        $this->assertSame($delGps, $this->planillaDelMonitor($d['conductor'])['uuid']);
    }

    public function test_no_mezcla_planillas_de_otros_vehiculos(): void
    {
        $a = $this->conductor('FFF666');
        $b = $this->conductor('GGG777');
        $planillaB = $this->planilla($b['vehiculo'], '2026-09-30', 1, $b['proyecto']);

        $this->assertNull($this->planillaDelMonitor($a['conductor']));
        $this->assertSame($planillaB, $this->planillaDelMonitor($b['conductor'])['uuid']);
    }

    public function test_una_planilla_subcontratada_se_cruza_por_placa(): void
    {
        $d = $this->conductor('SUB123');
        $planilla = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $this->empresa(), 'service_date' => '2026-09-30',
            'type_of_control_sheet' => 'SUBCONTRATADO', 'project_uuid' => $d['proyecto'], 'is_active' => true,
        ]);
        $this->insertar('service_internal_controls_subcontracted', [
            'service_delivery_control_sheet_uuid' => $planilla, 'vehicle_license_plate' => 'SUB123',
            'driver_name_and_surname' => 'Pedro Subcontratado',
        ]);
        $this->insertar('service_delivery_control_sheet_routes', [
            'service_delivery_control_sheet_uuid' => $planilla, 'order_index' => 1, 'origin' => 'A', 'destination' => 'B', 'is_active' => true,
        ]);

        $r = $this->planillaDelMonitor($d['conductor']);

        $this->assertSame($planilla, $r['uuid']);
        $this->assertSame('Pedro Subcontratado', $r['driver_name']);
    }

    public function test_el_nombre_del_conductor_sale_del_control_interno(): void
    {
        $d = $this->conductor('DIR456');
        $tercero = $this->insertar('third_parties', ['company_uuid' => $this->empresa(), 'first_name' => 'Ana', 'last_name' => 'Pérez']);
        $planilla = $this->planilla($d['vehiculo'], '2026-09-30', 1, $d['proyecto']);
        DB::table('service_internal_controls')->where('service_delivery_control_sheet_uuid', $planilla)->update(['third_party_uuid' => $tercero]);

        $this->assertSame('Ana Pérez', $this->planillaDelMonitor($d['conductor'])['driver_name']);
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_conductores(): void
    {
        $medir = fn () => app(LocationTrackingService::class)->getActiveDrivers($this->empresa());

        // 1 conductor con planilla y 1 sin ella
        $a = $this->conductor('HHH888');
        $this->planilla($a['vehiculo'], '2026-09-30', 1, $a['proyecto']);
        $this->conductor('III999');

        // 12 conductores más, la mayoría sin planilla de hoy (el caso que antes costaba 4 consultas c/u)
        $ampliar = function () {
            for ($i = 0; $i < 12; $i++) {
                $d = $this->conductor('ZZ'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
                if ($i % 3 === 0) {
                    $this->planilla($d['vehiculo'], '2026-09-30', 1, $d['proyecto']);
                }
            }
        };

        [$pocos, $muchos] = $this->assertConteoConstante($medir, $ampliar, 'Monitor de flota');
        $this->assertLessThanOrEqual(25, $pocos, "Monitor de flota hizo $pocos consultas con 2 conductores");
    }
}
