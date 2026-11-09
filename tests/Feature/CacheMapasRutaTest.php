<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pdf\RouteMapService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SPEC-015: el mapa estático del recorrido se procesa estrictamente en memoria
 * (sin almacenamiento ni caché de archivos en disco storage/app/route-maps).
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class CacheMapasRutaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.route_map.provider' => 'osm']);
    }

    private function servicio(?string $respuesta = 'PNG-FALSO'): object
    {
        return new class($respuesta) extends RouteMapService
        {
            public int $descargas = 0;

            public function __construct(public ?string $respuesta) {}

            protected function descargarImagen(string $url, int $timeout): ?string
            {
                $this->descargas++;

                return $this->respuesta;
            }
        };
    }

    private function puntos(float $ultimaLat = 4.62): Collection
    {
        return collect([[4.60, -74.00], [4.61, -74.01], [$ultimaLat, -74.02]])->map(fn($c, $i) => (object) [
            'latitude' => $c[0],
            'longitude' => $c[1],
            'speed' => 20,
            'recorded_at' => "2026-09-30 09:0{$i}:00",
        ]);
    }

    public function test_el_mapa_se_genera_en_memoria_sin_guardar_archivos_en_disco(): void
    {
        $servicio = $this->servicio();

        $a = $servicio->generar($this->puntos());

        $this->assertSame(1, $servicio->descargas);
        $this->assertSame(base64_encode('PNG-FALSO'), $a['imagen_base64']);
        $this->assertFalse($a['sin_datos']);
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));
    }

    public function test_descarga_fallida_utiliza_respaldo_gd_en_memoria_sin_archivos(): void
    {
        $servicio = $this->servicio(null);

        $r = $servicio->generar($this->puntos());

        $this->assertSame(1, $servicio->descargas);
        $this->assertNotNull($r['imagen_base64']);
        $this->assertFalse($r['sin_datos']);
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));
    }

    public function test_proveedor_none_no_invoca_descarga_y_usa_directamente_respaldo_gd(): void
    {
        config(['services.route_map.provider' => 'none']);
        $servicio = $this->servicio();

        $r = $servicio->generar($this->puntos());

        $this->assertSame(0, $servicio->descargas);
        $this->assertNotNull($r['imagen_base64']);
        $this->assertFalse($r['sin_datos']);
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));
    }

    public function test_sin_puntos_no_toca_la_red_ni_crea_archivos(): void
    {
        $servicio = $this->servicio();

        $r = $servicio->generar(collect());

        $this->assertTrue($r['sin_datos']);
        $this->assertNull($r['imagen_base64']);
        $this->assertSame(0, $servicio->descargas);
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));
    }

    public function test_un_solo_punto_genera_respaldo_gd_sin_descarga_ni_archivos(): void
    {
        $servicio = $this->servicio();
        $unPunto = collect([(object) [
            'latitude' => 4.60,
            'longitude' => -74.00,
            'speed' => 0,
            'recorded_at' => '2026-09-30 09:00:00',
        ]]);

        $r = $servicio->generar($unPunto);

        $this->assertSame(0, $servicio->descargas);
        $this->assertNotNull($r['imagen_base64']);
        $this->assertFalse($r['sin_datos']);
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));
    }
}
