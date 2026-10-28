<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Services\Pdf\ServiceControlSheetRows;
use App\Services\Pdf\ServiceControlSheetRowsOptions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candidato B — El builder de filas es la única definición de la forma de
 * cada fila del PDF, para diario, mensual y filtrado.
 *
 * Modelos construidos en memoria (sin disco ni red): lo único que se inyecta
 * son las firmas ya resueltas en base64.
 */
class ServiceControlSheetRowsTest extends TestCase
{
    use RefreshDatabase;

    private function hoja(array $attrs = [], array $rutas = []): ServiceDeliveryControlSheet
    {
        $hoja = new ServiceDeliveryControlSheet;
        $hoja->id = $attrs['id'] ?? 1;
        $hoja->uuid = $attrs['uuid'] ?? 'hoja-test';
        $hoja->service_date = $attrs['service_date'] ?? '2026-09-20';
        $hoja->start_time = $attrs['start_time'] ?? Carbon::parse('2026-09-20 06:00:00');
        $hoja->end_time = $attrs['end_time'] ?? Carbon::parse('2026-09-20 18:00:00');
        $hoja->total_hours = $attrs['total_hours'] ?? Carbon::parse('2026-09-20 11:00:00');
        $hoja->starting_kilometer = $attrs['starting_kilometer'] ?? '10000';
        $hoja->ending_kilometer = $attrs['ending_kilometer'] ?? '10300';
        $hoja->is_active = $attrs['is_active'] ?? false;
        $hoja->day_kind = $attrs['day_kind'] ?? 'operacion';
        $hoja->availability_reason = $attrs['availability_reason'] ?? null;
        $hoja->daily_route = $attrs['daily_route'] ?? null;
        $hoja->setRelation('routes', collect($rutas));

        return $hoja;
    }

    private function ruta(array $attrs = []): ServiceDeliveryControlSheetRoute
    {
        $ruta = new ServiceDeliveryControlSheetRoute;
        $ruta->id = $attrs['id'] ?? 1;
        $ruta->uuid = $attrs['uuid'] ?? 'ruta-test';
        $ruta->origin = $attrs['origin'] ?? 'Bodega';
        $ruta->destination = $attrs['destination'] ?? 'Cliente';
        // Los nulos explícitos se respetan: representan datos ausentes de verdad.
        $ruta->end_time = array_key_exists('end_time', $attrs) ? $attrs['end_time'] : '17:00:00';
        $ruta->ending_kilometer = array_key_exists('ending_kilometer', $attrs) ? $attrs['ending_kilometer'] : '10250';
        $ruta->funcionario_nombre = $attrs['funcionario_nombre'] ?? 'Ana Pérez';
        $ruta->funcionario_cc = $attrs['funcionario_cc'] ?? 'CC 1';
        $ruta->number_of_tolls = $attrs['number_of_tolls'] ?? 2;
        $ruta->total_toll_value = array_key_exists('total_toll_value', $attrs) ? $attrs['total_toll_value'] : 15000;
        $ruta->end_novelty = $attrs['end_novelty'] ?? null;

        return $ruta;
    }

    private function opciones(array $extras = []): ServiceControlSheetRowsOptions
    {
        return new ServiceControlSheetRowsOptions(
            proyecto: $extras['proyecto'] ?? fn () => 'Proyecto Prueba',
            rutasDe: $extras['rutasDe'] ?? fn ($hoja) => $hoja->routes,
            firmaHoja: $extras['firmaHoja'] ?? fn () => 'BASE64_HOJA',
            firmasRuta: $extras['firmasRuta'] ?? fn ($hoja, $route) => ['funcionario' => 'BASE64_RUTA', 'conductor' => null],
            numeroEntero: $extras['numeroEntero'] ?? true,
            conRutasDetalle: $extras['conRutasDetalle'] ?? true,
            firmaCoordinador: $extras['firmaCoordinador'] ?? null,
            firmaConductor: $extras['firmaConductor'] ?? null,
        );
    }

    public function test_recorrido_completo_usa_cierre_propio_y_respaldo_de_hoja(): void
    {
        $hoja = $this->hoja([], [$this->ruta()]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $this->assertCount(1, $resultado['dias']);
        $this->assertSame(1, $resultado['totalRecorridos']);
        $fila = $resultado['dias'][0];
        $this->assertSame(20, $fila['numero']);
        $this->assertSame('20/09/2026', $fila['fecha_completa']);
        $this->assertSame('Bodega - Cliente', $fila['ruta']);
        // Cierre propio del recorrido, no el global.
        $this->assertSame('17:00', $fila['hora_fin']);
        $this->assertSame('10250', $fila['km_final']);
        $this->assertSame('250', $fila['km_total']);
        $this->assertSame('17:00', $fila['hora_fin_recorrido']);
        $this->assertSame('10250', $fila['km_final_recorrido']);
        // Detalle con funcionario + peajes; firma del recorrido.
        $this->assertStringContainsString('Funcionario CC CC 1 - Ana Pérez', $fila['detalle_cierre']);
        $this->assertStringContainsString('Peajes: 2 ($15.000)', $fila['detalle_cierre']);
        $this->assertSame('BASE64_RUTA', $fila['firma_funcionario']);
    }

    public function test_sin_firma_de_recorrido_respalda_la_de_hoja(): void
    {
        $hoja = $this->hoja([], [$this->ruta()]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'firmasRuta' => fn () => ['funcionario' => null, 'conductor' => null],
        ]));

        $this->assertSame('BASE64_HOJA', $resultado['dias'][0]['firma_funcionario']);
    }

    public function test_sin_firma_de_funcionario_la_fila_usa_la_del_coordinador(): void
    {
        $hoja = $this->hoja([], [$this->ruta()]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'firmaHoja' => fn () => null,
            'firmasRuta' => fn () => ['funcionario' => null, 'conductor' => null],
            'firmaCoordinador' => fn () => 'BASE64_COORDINADOR',
        ]));

        $this->assertSame('BASE64_COORDINADOR', $resultado['dias'][0]['firma_funcionario']);
    }

    public function test_la_firma_del_funcionario_tiene_prioridad_sobre_la_del_coordinador(): void
    {
        $hoja = $this->hoja([], [$this->ruta()]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'firmaCoordinador' => fn () => 'BASE64_COORDINADOR',
        ]));

        $this->assertSame('BASE64_RUTA', $resultado['dias'][0]['firma_funcionario']);
    }

    public function test_la_fila_muestra_la_firma_del_conductor_del_recorrido(): void
    {
        $hoja = $this->hoja([], [$this->ruta()]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'firmasRuta' => fn () => ['funcionario' => 'BASE64_RUTA', 'conductor' => 'BASE64_CONDUCTOR_RUTA'],
            'firmaConductor' => fn () => 'BASE64_CONDUCTOR_HOJA',
        ]));

        $this->assertSame('BASE64_CONDUCTOR_RUTA', $resultado['dias'][0]['firma_conductor_recorrido']);
    }

    public function test_sin_firma_de_conductor_del_recorrido_usa_la_de_la_hoja(): void
    {
        $hoja = $this->hoja([], [$this->ruta()]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'firmaConductor' => fn () => 'BASE64_CONDUCTOR_HOJA',
        ]));

        $this->assertSame('BASE64_CONDUCTOR_HOJA', $resultado['dias'][0]['firma_conductor_recorrido']);
    }

    public function test_disponibilidad_tambien_muestra_la_firma_del_conductor(): void
    {
        $hoja = $this->hoja(['day_kind' => 'disponibilidad', 'daily_route' => null], []);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'firmaConductor' => fn () => 'BASE64_CONDUCTOR_HOJA',
        ]));

        $this->assertSame('BASE64_CONDUCTOR_HOJA', $resultado['dias'][0]['firma_conductor_recorrido']);
    }

    public function test_disponibilidad_imprime_motivo_y_sin_firma_funcionario(): void
    {
        $hoja = $this->hoja([
            'day_kind' => 'disponibilidad',
            'availability_reason' => 'Vehículo en mantenimiento',
        ]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones([
            'numeroEntero' => false,
            'conRutasDetalle' => false,
        ]));

        $fila = $resultado['dias'][0];
        $this->assertSame('20', $fila['numero']);
        $this->assertSame('VEHÍCULO EN DISPONIBILIDAD — Vehículo en mantenimiento', $fila['ruta']);
        $this->assertNull($fila['firma_funcionario']);
        $this->assertSame(0, $resultado['totalRecorridos']);
        $this->assertSame([], $resultado['rutasDetalle']);
    }

    public function test_numero_respeta_el_formato_de_cada_reporte(): void
    {
        $hoja = $this->hoja(['service_date' => '2026-09-05'], [$this->ruta()]);

        $entero = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones(['numeroEntero' => true]));
        $texto = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones(['numeroEntero' => false]));

        $this->assertSame(5, $entero['dias'][0]['numero']);
        $this->assertSame('05', $texto['dias'][0]['numero']);
    }

    public function test_totales_se_suman_por_hoja_y_no_por_recorrido(): void
    {
        // Dos hojas; la primera con dos recorridos. El consolidado debe contar
        // 2 días y sumar horas/km una sola vez por hoja (no por fila).
        $hojaUno = $this->hoja([
            'id' => 1,
            'total_hours' => Carbon::parse('2026-09-20 11:30:00'),
            'starting_kilometer' => '10000',
            'ending_kilometer' => '10300',
        ], [$this->ruta(['id' => 1]), $this->ruta(['id' => 2])]);
        $hojaDos = $this->hoja([
            'id' => 2,
            'service_date' => '2026-09-21',
            'is_active' => true,
            'total_hours' => Carbon::parse('2026-09-21 08:00:00'),
            'starting_kilometer' => '10300',
            'ending_kilometer' => '10450',
        ]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hojaUno, $hojaDos], $this->opciones());

        $this->assertCount(3, $resultado['dias']);
        $this->assertSame(2, $resultado['totales']['dias']);
        $this->assertSame(1, $resultado['totales']['dias_cerrados']);
        $this->assertSame(19 * 60 + 30, $resultado['totales']['horas_minutos']);
        $this->assertSame('19:30', $resultado['totales']['horas']);
        $this->assertSame(450.0, $resultado['totales']['km']);
    }

    public function test_novedad_excusa_hora_y_km_sin_borrar_firmas(): void
    {
        $hoja = $this->hoja([], [$this->ruta([
            'end_time' => null,
            'ending_kilometer' => null,
            'end_novelty' => 'Vía cerrada por derrumbe',
        ])]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $fila = $resultado['dias'][0];
        // Sin cierre propio: conserva los globales de la hoja.
        $this->assertSame('18:00', $fila['hora_fin']);
        $this->assertSame('10300', $fila['km_final']);
        $this->assertSame('300', $fila['km_total']);
        $this->assertStringContainsString('Novedad: Vía cerrada por derrumbe', $fila['detalle_cierre']);
    }

    public function test_km_total_se_calcula_por_tramo_y_no_acumulado(): void
    {
        // Hoja 900 → 1200 con dos recorridos que cierran en 1000 y 1200.
        $hoja = $this->hoja([
            'starting_kilometer' => '900',
            'ending_kilometer' => '1200',
        ], [
            $this->ruta(['id' => 1, 'ending_kilometer' => '1000']),
            $this->ruta(['id' => 2, 'ending_kilometer' => '1200']),
        ]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $this->assertSame('900', $resultado['dias'][0]['km_inicial']);
        $this->assertSame('1000', $resultado['dias'][0]['km_final']);
        $this->assertSame('100', $resultado['dias'][0]['km_total']);
        $this->assertSame('1000', $resultado['dias'][1]['km_inicial']);
        $this->assertSame('1200', $resultado['dias'][1]['km_final']);
        $this->assertSame('200', $resultado['dias'][1]['km_total']);
        $this->assertSame(300.0, $resultado['totales']['km']);
    }

    public function test_turno_mayor_a_24_horas_no_rompe_el_pdf(): void
    {
        $hoja = $this->hoja(['id' => 2, 'is_active' => true]);
        $hoja->setRawAttributes(array_merge($hoja->getAttributes(), ['total_hours' => '25:30:00']));

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $this->assertSame('25:30', $resultado['dias'][0]['total_hours']);
        $this->assertSame((25 * 60) + 30, $resultado['totales']['horas_minutos']);
        $this->assertSame('25:30', $resultado['totales']['horas']);
    }

    public function test_kilometraje_invertido_no_muestra_negativos(): void
    {
        $hoja = $this->hoja([
            'starting_kilometer' => '1200',
            'ending_kilometer' => '900',
        ]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $this->assertSame('0', $resultado['dias'][0]['km_total']);
        $this->assertSame(0.0, $resultado['totales']['km']);
    }

    public function test_el_descanso_conserva_su_calculo_actual(): void
    {
        // 06:00–18:00 con 11:00 trabajadas: 1 hora de descanso (la semántica
        // del descanso calculado no se cambia en esta corrección).
        $hoja = $this->hoja();

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $this->assertSame('12:00', $resultado['dias'][0]['descanso_inicio']);
        $this->assertSame('13:00', $resultado['dias'][0]['descanso_fin']);
    }

    public function test_kilometraje_con_separador_de_miles_se_interpreta_bien(): void
    {
        $hoja = $this->hoja([
            'starting_kilometer' => '12.000',
            'ending_kilometer' => '12.345',
        ]);

        $resultado = app(ServiceControlSheetRows::class)->armarDias([$hoja], $this->opciones());

        $this->assertSame('12000', $resultado['dias'][0]['km_inicial']);
        $this->assertSame('12345', $resultado['dias'][0]['km_final']);
        $this->assertSame('345', $resultado['dias'][0]['km_total']);
        $this->assertSame(345.0, $resultado['totales']['km']);
    }
}
