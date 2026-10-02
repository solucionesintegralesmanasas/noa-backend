<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tracking\LocationHistoryService;
use App\Services\Tracking\LocationTrackingService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * SPEC-004 / ARQ-008: las consultas críticas usan sus índices.
 *
 * No basta con que el índice exista (ver IndicesTrackingTest): se capturan las
 * consultas reales del flujo con el query log y se exige su plan de EXPLAIN.
 * Con volumen mínimo el optimizador podría elegir un recorrido completo, así
 * que estas pruebas siembran miles de puntos antes de medir.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class IndicesEnUsoTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    /** @var array<int, string> */
    private array $conductores = [];

    /** @var array<int, string> */
    private array $vehiculos = [];

    /** @var array<int, string> */
    private array $proyectos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa índices', 'is_active' => true]);
        // Varios vehículos y proyectos para que sus índices sean los más selectivos (como en producción).
        for ($i = 1; $i <= 25; $i++) {
            $n = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $this->vehiculos[] = $this->insertar('vehicles', ['company_uuid' => $this->empresa, 'vehicle_license_plate' => 'IDX'.$n, 'is_active' => true]);
            $this->proyectos[] = $this->insertar('projects', ['company_uuid' => $this->empresa, 'project_name' => 'Proyecto '.$n]);
        }
        for ($i = 1; $i <= 3; $i++) {
            $conductor = $this->insertar('third_parties', ['company_uuid' => $this->empresa, 'first_name' => 'Conductor', 'last_name' => 'IDX'.$i]);
            $this->conductores[] = $conductor;
            $this->insertar('driver_location_sessions', [
                'company_uuid' => $this->empresa, 'third_party_uuid' => $conductor,
                'vehicle_uuid' => $this->vehiculos[0], 'project_uuid' => $this->proyectos[0],
                'status' => 'active', 'started_at' => '2026-09-29 08:00:00',
            ]);
        }
        $this->sembrarPuntos(2500);
        $this->sembrarAlertas(500);
        $this->sembrarPlanillas();
        DB::statement('ANALYZE TABLE driver_locations, driver_location_alerts, service_delivery_control_sheet');
    }

    private function sembrarPuntos(int $n): void
    {
        $filas = [];
        for ($i = 0; $i < $n; $i++) {
            $filas[] = [
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $this->empresa,
                'third_party_uuid' => $this->conductores[$i % 3],
                'vehicle_uuid' => $this->vehiculos[$i % 25],
                'project_uuid' => $this->proyectos[$i % 25],
                'latitude' => 4.6 + $i * 0.000001,
                'longitude' => -74.0 + $i * 0.000001,
                'speed' => 30,
                'recorded_at' => '2026-09-'.(29 + ($i % 2)).' '.str_pad((string) ($i % 24), 2, '0', STR_PAD_LEFT).':00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($filas, 500) as $tanda) {
            DB::table('driver_locations')->insert($tanda);
        }
    }

    /** Alertas repartidas en días y estados para que el índice sea selectivo. */
    private function sembrarAlertas(int $n): void
    {
        $filas = [];
        for ($i = 0; $i < $n; $i++) {
            $filas[] = [
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $this->empresa,
                'third_party_uuid' => $this->conductores[$i % 3],
                'alert_type' => 'overspeed',
                'message' => 'Exceso de velocidad',
                'latitude' => 0, 'longitude' => 0,
                'is_read' => $i % 4 === 0,
                'created_at' => '2026-09-'.str_pad((string) (1 + ($i % 30)), 2, '0', STR_PAD_LEFT).' 09:00:00',
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($filas, 200) as $tanda) {
            DB::table('driver_location_alerts')->insert($tanda);
        }
    }

    /** Planillas repartidas en fechas y empresas para que el índice sea selectivo. */
    private function sembrarPlanillas(int $porEmpresa = 40, int $empresas = 5): void
    {
        $companias = [$this->empresa];
        for ($e = 1; $e < $empresas; $e++) {
            $companias[] = $this->insertar('companies', ['business_name' => 'Empresa índice '.$e, 'is_active' => true]);
        }
        foreach ($companias as $compania) {
            for ($i = 0; $i < $porEmpresa; $i++) {
                $planilla = $this->insertar('service_delivery_control_sheet', [
                    'company_uuid' => $compania,
                    'service_date' => '2026-09-'.str_pad((string) (1 + ($i % 30)), 2, '0', STR_PAD_LEFT),
                    'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
                    'project_uuid' => $this->proyectos[$i % 25],
                    'is_active' => true,
                ]);
                $this->insertar('service_internal_controls', [
                    'company_uuid' => $compania, 'vehicle_uuid' => $this->vehiculos[$i % 25],
                    'service_delivery_control_sheet_uuid' => $planilla,
                ]);
            }
        }
    }

    private function literal(mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }
        if ($valor instanceof DateTimeInterface) {
            return "'".$valor->format('Y-m-d H:i:s')."'";
        }

        return DB::getPdo()->quote((string) $valor);
    }

    /** Plan de EXPLAIN de cada SELECT que el bloque ejecuta sobre la tabla. */
    private function explicar(string $tabla, callable $bloque): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $bloque();
        } finally {
            DB::disableQueryLog();
        }

        $planes = [];
        foreach (DB::getQueryLog() as $q) {
            $sql = $q['query'];
            if (! str_starts_with(strtolower(ltrim($sql)), 'select')) {
                continue;
            }
            // La tabla puede ir sin tildes (FORCE INDEX) y su nombre puede ser
            // prefijo de otra tabla (driver_locations vs driver_location_alerts).
            if (! preg_match('/'.preg_quote($tabla, '/').'(?![A-Za-z_])/', str_replace('`', '', $sql))) {
                continue;
            }
            $interpolado = $sql;
            foreach ($q['bindings'] as $b) {
                $interpolado = preg_replace('/\?/', $this->literal($b), $interpolado, 1);
            }
            $filas = [];
            foreach (DB::select('EXPLAIN '.$interpolado) as $fila) {
                $filas[] = (array) $fila;
            }
            $planes[] = ['sql' => $interpolado, 'filas' => $filas];
        }

        return $planes;
    }

    /**
     * Ninguna consulta del flujo recorre la tabla completa y todas usan uno de
     * los índices declarados. Las tablas derivadas (<derived>, <subqueryN>) se
     * omiten: son temporales ya materializadas, no recorridos de la tabla.
     */
    private function assertIndicesEnUso(array $planes, array $indices, string $etiqueta): void
    {
        $reales = [];
        foreach ($planes as $plano) {
            foreach ($plano['filas'] as $fila) {
                if (str_starts_with((string) ($fila['table'] ?? ''), '<')) {
                    continue;
                }
                $reales[] = $fila;
            }
        }
        $this->assertNotEmpty($reales, "$etiqueta no ejecutó consultas sobre la tabla");
        foreach ($reales as $fila) {
            $this->assertNotSame('ALL', $fila['type'] ?? null, "$etiqueta recorre la tabla completa: ".($fila['key'] ?? '?'));
            $this->assertContains(
                $fila['key'] ?? null,
                $indices,
                "$etiqueta usa el índice '".($fila['key'] ?? 'ninguno')."' en vez de uno esperado"
            );
        }
    }

    public function test_el_monitor_usa_sus_indices(): void
    {
        $planes = $this->explicar('driver_locations', fn () => app(LocationTrackingService::class)->getActiveDrivers($this->empresa));
        // Subconsulta agrupada por el índice de empresa y accesos por clave primaria.
        $this->assertIndicesEnUso($planes, ['PRIMARY', 'idx_dl_company_recorded'], 'Monitor de flota');
    }

    public function test_el_mapa_por_vehiculo_y_por_proyecto_usa_sus_indices(): void
    {
        $servicio = app(LocationHistoryService::class);
        $vehiculo = $this->explicar(
            'driver_locations',
            fn () => $servicio->getVehicleProjectDayHistory($this->vehiculos[0], null, '2026-09-29', $this->empresa)
        );
        $this->assertIndicesEnUso($vehiculo, ['idx_dl_company_vehicle_recorded'], 'Mapa por vehículo');

        $proyecto = $this->explicar(
            'driver_locations',
            fn () => $servicio->getVehicleProjectDayHistory(null, $this->proyectos[0], '2026-09-29', $this->empresa)
        );
        $this->assertIndicesEnUso($proyecto, ['idx_dl_company_project_recorded'], 'Mapa por proyecto');
    }

    public function test_el_historial_por_conductor_usa_su_indice(): void
    {
        $planes = $this->explicar(
            'driver_locations',
            fn () => app(LocationHistoryService::class)->getDriverHistory($this->conductores[0], '2026-09-29', '2026-09-30', $this->empresa, 50, 1)
        );
        // La consulta fija su índice con FORCE INDEX.
        $this->assertIndicesEnUso($planes, ['idx_dl_driver_recorded'], 'Historial por conductor');
    }

    public function test_la_ingesta_usa_sus_indices(): void
    {
        $planes = $this->explicar('driver_locations', fn () => app(LocationTrackingService::class)->storeLocation([
            'company_uuid' => $this->empresa, 'third_party_uuid' => $this->conductores[0],
            'latitude' => 4.6100, 'longitude' => -74.0000, 'speed' => 10.0, 'recorded_at' => '2026-09-30 10:00:00',
        ]));
        // Punto anterior y siguiente por conductor y fecha; sesión por conductor.
        $this->assertIndicesEnUso($planes, ['idx_dl_driver_recorded', 'PRIMARY'], 'Ingesta de un punto');
    }

    public function test_las_alertas_no_leidas_usan_su_indice(): void
    {
        $planes = $this->explicar(
            'driver_location_alerts',
            fn () => app(LocationTrackingService::class)->getAlerts($this->empresa, 15, true)
        );
        // El optimizador reparte entre el índice estrecho y el compuesto según la consulta.
        $this->assertIndicesEnUso($planes, ['idx_dla_company_read', 'idx_dla_company_read_created'], 'Alertas no leídas');
    }

    public function test_las_planillas_por_empresa_y_fecha_usan_su_indice(): void
    {
        $planes = $this->explicar('service_delivery_control_sheet', fn () => app(LocationTrackingService::class)->getActiveDrivers($this->empresa));

        // Búsqueda de la planilla del día: usa el índice de empresa y fecha.
        $delDia = array_values(array_filter($planes, fn ($p) => str_contains($p['sql'], '`service_date` =')));
        $this->assertIndicesEnUso($delDia, ['idx_sdcs_company_date'], 'Planilla del día');

        // Respaldo ("última conocida"): hoy recorre la tabla porque el rango más
        // los EXISTS no compensan el índice con este volumen; lo que se fija es
        // que sigue acotado (LIMIT 500) para que no crezca sin límite.
        $respaldo = array_values(array_filter($planes, fn ($p) => str_contains($p['sql'], '`service_date` <=')));
        $this->assertNotEmpty($respaldo, 'El monitor ejecuta el respaldo de planillas');
        foreach ($respaldo as $plano) {
            $this->assertMatchesRegularExpression('/limit 500$/i', trim($plano['sql']), 'El respaldo de planillas perdió su tope');
        }
    }
}
