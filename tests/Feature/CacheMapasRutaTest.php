<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pdf\RouteMapService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ARQ-006: el mapa estático del recorrido se descarga una sola vez por trazado exacto.
 * La red se sustituye por un doble; la clave de caché es el hash de la URL completa.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class CacheMapasRutaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.route_map.cache_enabled' => true, 'services.route_map.cache_days' => 30]);
    }

    private function servicio(?string $respuesta = 'PNG-FALSO'): object
    {
        return new class($respuesta) extends RouteMapService
        {
            public int $descargas = 0;

            public bool $limpiar = false;

            public function __construct(public ?string $respuesta) {}

            protected function descargarImagen(string $url, int $timeout): ?string
            {
                $this->descargas++;

                return $this->respuesta;
            }

            protected function tocaLimpiar(): bool
            {
                return $this->limpiar;
            }
        };
    }

    private function puntos(float $ultimaLat = 4.62): Collection
    {
        return collect([[4.60, -74.00], [4.61, -74.01], [$ultimaLat, -74.02]])->map(fn ($c, $i) => (object) [
            'latitude' => $c[0], 'longitude' => $c[1], 'speed' => 20, 'recorded_at' => "2026-09-30 09:0{$i}:00",
        ]);
    }

    public function test_el_mismo_trazado_se_descarga_una_sola_vez(): void
    {
        $servicio = $this->servicio();

        $a = $servicio->generar($this->puntos());
        $b = $servicio->generar($this->puntos());

        $this->assertSame(1, $servicio->descargas);
        $this->assertSame(base64_encode('PNG-FALSO'), $a['imagen_base64']);
        $this->assertSame($a['imagen_base64'], $b['imagen_base64']);
        $this->assertCount(1, Storage::disk('local')->files('route-maps'));
    }

    public function test_un_punto_nuevo_cambia_la_clave_y_no_sirve_un_mapa_obsoleto(): void
    {
        $servicio = $this->servicio();

        $servicio->generar($this->puntos(4.62));
        $servicio->generar($this->puntos(4.65)); // el vehículo avanzó

        $this->assertSame(2, $servicio->descargas);
        $this->assertCount(2, Storage::disk('local')->files('route-maps'));
    }

    public function test_una_descarga_fallida_no_se_cachea_y_se_reintenta(): void
    {
        $servicio = $this->servicio(null);

        $r = $servicio->generar($this->puntos());
        $this->assertNotNull($r['imagen_base64']); // respaldo GD offline
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));

        $servicio->respuesta = 'PNG-FALSO';
        $servicio->generar($this->puntos());

        $this->assertSame(2, $servicio->descargas);
        $this->assertCount(1, Storage::disk('local')->files('route-maps'));
    }

    public function test_con_la_cache_desactivada_siempre_descarga(): void
    {
        config(['services.route_map.cache_enabled' => false]);
        $servicio = $this->servicio();

        $servicio->generar($this->puntos());
        $servicio->generar($this->puntos());

        $this->assertSame(2, $servicio->descargas);
        $this->assertCount(0, Storage::disk('local')->files('route-maps'));
    }

    public function test_la_limpieza_borra_solo_lo_antiguo(): void
    {
        $servicio = $this->servicio();
        $servicio->limpiar = true;
        $disco = Storage::disk('local');
        $disco->put('route-maps/viejo.png', 'x');
        $disco->put('route-maps/reciente.png', 'x');
        touch($disco->path('route-maps/viejo.png'), now()->subDays(45)->getTimestamp());

        $servicio->generar($this->puntos()); // al guardar uno nuevo se limpia

        $this->assertFalse($disco->exists('route-maps/viejo.png'));
        $this->assertTrue($disco->exists('route-maps/reciente.png'));
        $this->assertCount(2, $disco->files('route-maps')); // reciente + el nuevo
    }

    public function test_sin_puntos_no_toca_la_red_ni_la_cache(): void
    {
        $servicio = $this->servicio();

        $r = $servicio->generar(collect());

        $this->assertTrue($r['sin_datos']);
        $this->assertSame(0, $servicio->descargas);
    }
}
