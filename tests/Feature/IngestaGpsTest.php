<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DriverLocation;
use App\Models\DriverLocationAlert;
use App\Services\Tracking\LocationTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * ARQ-004R: coste por punto GPS ingerido. Fija el comportamiento (sesión, distancia, resumen diario,
 * alertas de geocerca) y que el número de consultas NO dependa de cuántas geocercas tenga la empresa
 * (antes: 5 + 5 × N según el plan; el estado previo se consultaba hasta 2 veces por geocerca).
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class IngestaGpsTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    private string $conductor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conductor = $this->nuevoConductor();
    }

    /** Tercero real (la clave foránea de driver_locations exige que exista). */
    private function nuevoConductor(): string
    {
        return $this->insertar('third_parties', ['company_uuid' => $this->empresa(), 'first_name' => 'Conductor', 'last_name' => 'GPS']);
    }

    /** Inserta una fila rellenando las columnas obligatorias sin valor por defecto. */
    private function insertar(string $tabla, array $datos): string
    {
        $uuid = $datos['uuid'] ?? (string) Str::uuid();
        $datos['uuid'] = $uuid;
        $columnas = DB::select(
            'select column_name n, data_type t, column_type ct from information_schema.columns
             where table_schema = database() and table_name = ? and is_nullable = "NO"
             and column_default is null and extra not like "%auto_increment%"',
            [$tabla]
        );
        foreach ($columnas as $c) {
            if (array_key_exists($c->n, $datos)) {
                continue;
            }
            $datos[$c->n] = match (true) {
                in_array($c->t, ['char', 'varchar'], true) => $c->n === 'email' ? Str::random(6).'@x.test' : (str_ends_with($c->n, 'uuid') ? (string) Str::uuid() : 'x'),
                in_array($c->t, ['text', 'longtext', 'mediumtext'], true) => 'x',
                $c->t === 'enum' => explode("','", trim(substr($c->ct, 5, -1), "'"))[0],
                $c->t === 'date' => '2026-09-30',
                in_array($c->t, ['datetime', 'timestamp'], true) => '2026-09-30 10:00:00',
                $c->t === 'json' => '[]',
                default => 0,
            };
        }
        Schema::disableForeignKeyConstraints();
        DB::table($tabla)->insert($datos + ['created_at' => now(), 'updated_at' => now()]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }
    private function empresa(): string
    {
        return $this->empresa ??= $this->insertar('companies', ['business_name' => 'Empresa GPS', 'is_active' => true]);
    }

    private function sesion(): string
    {
        return $this->insertar('driver_location_sessions', [
            'company_uuid' => $this->empresa(), 'third_party_uuid' => $this->conductor,
            'status' => 'active', 'started_at' => '2026-09-30 08:00:00', 'total_distance_km' => 0, 'total_points' => 0,
        ]);
    }

    /** Geocerca circular de 200 m alrededor de (4.6000, -74.0000). */
    private function geocerca(array $extra = []): string
    {
        return $this->insertar('geofences', array_merge([
            'company_uuid' => $this->empresa(), 'name' => 'Zona '.Str::random(4), 'type' => 'circle',
            'center_lat' => 4.6000, 'center_lng' => -74.0000, 'radius_meters' => 200,
            'alert_on_enter' => true, 'alert_on_exit' => true, 'is_active' => true,
        ], $extra));
    }

    private function punto(float $lat, float $lng, string $hora, float $velocidad = 10.0): DriverLocation
    {
        return app(LocationTrackingService::class)->storeLocation([
            'company_uuid' => $this->empresa(), 'third_party_uuid' => $this->conductor,
            'latitude' => $lat, 'longitude' => $lng, 'speed' => $velocidad, 'recorded_at' => "2026-09-30 $hora",
        ]);
    }

    /** @return array<int, string> tipos de alerta generados, en orden */
    private function alertas(): array
    {
        return DriverLocationAlert::withoutGlobalScopes()->orderBy('id')->pluck('alert_type')->all();
    }

    // ------------------------------------------------------------------ comportamiento

    public function test_la_sesion_activa_acumula_puntos_y_distancia(): void
    {
        $sesion = $this->sesion();

        $this->punto(4.6000, -74.0000, '09:00:00');
        $this->punto(4.6100, -74.0000, '09:01:00'); // ~1 112 m al norte

        $fila = DB::table('driver_location_sessions')->where('uuid', $sesion)->first();
        $this->assertSame(2, (int) $fila->total_points);
        $this->assertEqualsWithDelta(1.112, (float) $fila->total_distance_km, 0.01);
    }

    public function test_el_segundo_punto_guarda_su_distancia_y_el_resumen_diario_cuenta_ambos(): void
    {
        $this->punto(4.6000, -74.0000, '09:00:00');
        $segundo = $this->punto(4.6100, -74.0000, '09:01:00');

        $this->assertEqualsWithDelta(1112.0, (float) $segundo->distance_meters, 10.0);
        $resumen = DB::table('driver_location_daily_stats')->where('third_party_uuid', $this->conductor)->first();
        $this->assertSame(2, (int) $resumen->total_points);
    }

    public function test_un_punto_desordenado_corrige_la_distancia_del_siguiente(): void
    {
        $this->sesion();
        $a = $this->punto(4.6000, -74.0000, '09:00:00');
        $c = $this->punto(4.6200, -74.0000, '09:10:00');
        $distanciaAC = (float) $c->fresh()->distance_meters; // A -> C directo

        $this->punto(4.6100, -74.0000, '09:05:00'); // B llega tarde, entre A y C

        $this->assertEqualsWithDelta(1112.0, (float) $c->fresh()->distance_meters, 10.0); // ahora B -> C
        $this->assertGreaterThan((float) $c->fresh()->distance_meters, $distanciaAC);
        $this->assertSame($a->id, DriverLocation::withoutGlobalScopes()->orderBy('recorded_at')->first()->id);
    }

    public function test_alerta_al_entrar_y_al_salir_de_la_geocerca(): void
    {
        $this->geocerca();

        $this->punto(4.6100, -74.0000, '09:00:00'); // fuera (~1 km)
        $this->punto(4.6000, -74.0000, '09:01:00'); // dentro -> entrada
        $this->punto(4.6001, -74.0000, '09:02:00'); // sigue dentro -> sin alerta
        $this->punto(4.6100, -74.0000, '09:03:00'); // fuera -> salida

        $this->assertSame(['geofence_enter', 'geofence_exit'], $this->alertas());
    }

    public function test_alerta_de_exceso_de_velocidad_dentro_de_la_geocerca(): void
    {
        $this->geocerca(['max_speed_kmh' => 40, 'alert_on_enter' => false, 'alert_on_exit' => false]);

        $this->punto(4.6000, -74.0000, '09:00:00', 30.0);
        $this->punto(4.6001, -74.0000, '09:01:00', 80.0);

        $this->assertSame(['overspeed'], $this->alertas());
    }

    public function test_el_primer_punto_de_un_conductor_dentro_de_una_geocerca_cuenta_como_entrada(): void
    {
        $this->geocerca();

        $this->punto(4.6000, -74.0000, '09:00:00');

        $this->assertSame(['geofence_enter'], $this->alertas());
    }

    public function test_las_geocercas_de_otra_empresa_no_generan_alertas(): void
    {
        $otra = $this->insertar('companies', ['business_name' => 'Otra', 'is_active' => true]);
        $this->geocerca(['company_uuid' => $otra]);

        $this->punto(4.6100, -74.0000, '09:00:00');
        $this->punto(4.6000, -74.0000, '09:01:00');

        $this->assertSame([], $this->alertas());
    }

    // ------------------------------------------------------------------ coste

    /** Consultas de un punto que no es el primero del conductor (el caso normal). */
    private function consultasDeUnPunto(): int
    {
        $this->sesion();
        $this->punto(4.6100, -74.0000, '09:00:00');

// Fuera de toda geocerca: sin alertas.
        return $this->contarConsultas(fn () => $this->punto(4.6105, -74.0000, '09:01:00'));
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_geocercas(): void
    {
        // Mismo escenario con 6 geocercas lejanas que vigilan entrada y salida.
        $ampliar = function () {
            $this->conductor = $this->nuevoConductor();
            for ($i = 0; $i < 6; $i++) {
                $this->geocerca(['center_lat' => 4.9 + $i / 100, 'center_lng' => -74.5]);
            }
        };

        // La primera medición siembra el escenario sin geocercas; la ampliación lo repite con 6.
        $sin = $this->contarConsultas($medir);
        $ampliar();
        $con = $this->contarConsultas($medir);

        $this->assertSame($sin, $con, "Sin geocercas: $sin consultas; con 6: $con");
    }

    public function test_un_punto_hace_pocas_consultas(): void
    {
        $n = $this->consultasDeUnPunto();

        $this->assertLessThanOrEqual(7, $n, "Un punto GPS hizo $n consultas");
    }

    public function test_los_puntos_y_alertas_no_escriben_en_el_registro_de_actividad(): void
    {
        $this->geocerca();
        $this->punto(4.6100, -74.0000, '09:00:00');
        $this->punto(4.6000, -74.0000, '09:01:00'); // genera una alerta

        $filas = DB::table('activity_log')
            ->whereIn('subject_type', [DriverLocation::class, DriverLocationAlert::class])
            ->count();

        $this->assertSame(0, $filas, 'Cada punto/alerta duplicaba su escritura en activity_log');
    }
}
