<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Services\Pdf\ServiceControlSheetRows;
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

    private function opciones(array $extras = []): array
    {
        return array_merge([
            'proyecto' => fn () => 'Proyecto Prueba',
            'rutasDe' => fn ($hoja) => $hoja->routes,
            'firmaHoja' => fn () => 'BASE64_HOJA',
            'firmasRuta' => fn ($hoja, $route) => ['funcionario' => 'BASE64_RUTA', 'conductor' => null],
            'numeroEntero' => true,
            'conRutasDetalle' => true,
        ], $extras);
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
}
