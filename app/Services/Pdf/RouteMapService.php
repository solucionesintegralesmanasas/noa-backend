<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Utils\Logger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Genera la imagen del recorrido GPS del vehículo en proyecto para el PDF diario.
 *
 * Estrategia híbrida:
 * - Intenta descargar un mapa estático OSM con el trazado (fondo real de calles).
 * - Si falla la red o no hay puntos suficientes, genera un PNG de respaldo offline con GD.
 *
 * La salida siempre es PNG en base64 listo para DomPDF: data:image/png;base64,...
 * (DomPDF no renderiza SVG en data-uri de forma fiable, por eso el respaldo es PNG).
 */
class RouteMapService
{
    private const MAX_POINTS_URL = 100;

    private const MAP_WIDTH = 900;

    private const MAP_HEIGHT = 380;

    /**
     * Genera el mapa del recorrido a partir de los puntos GPS del día.
     *
     * @param  Collection  $points  Colección con latitude, longitude, speed, recorded_at
     * @return array{imagen_base64: string|null, mime: string, sin_datos: bool, stats: array}
     */
    public function generar(Collection $points): array
    {
        $stats = $this->calcularStats($points);

        if ($points->isEmpty()) {
            return [
                'imagen_base64' => null,
                'mime' => 'image/png',
                'sin_datos' => true,
                'stats' => $stats,
            ];
        }

        $coords = $points
            ->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])
            ->filter(fn ($c) => $c[0] !== 0.0 && $c[1] !== 0.0)
            ->values();

        if ($coords->isEmpty()) {
            return [
                'imagen_base64' => null,
                'mime' => 'image/png',
                'sin_datos' => true,
                'stats' => $stats,
            ];
        }

        // Un solo punto: no hay polyline, se muestra marcador único con respaldo PNG.
        if ($coords->count() === 1) {
            return [
                'imagen_base64' => $this->generarPngRespaldo($coords),
                'mime' => 'image/png',
                'sin_datos' => false,
                'stats' => $stats,
            ];
        }

        try {
            $base64 = $this->descargarMapaOsm($coords);
            if (! empty($base64)) {
                return [
                    'imagen_base64' => $base64,
                    'mime' => 'image/png',
                    'sin_datos' => false,
                    'stats' => $stats,
                ];
            }
        } catch (\Throwable $e) {
            Logger::warning('RouteMapService: fallo mapa OSM, se usa respaldo PNG: '.$e->getMessage());
        }

        return [
            'imagen_base64' => $this->generarPngRespaldo($coords),
            'mime' => 'image/png',
            'sin_datos' => false,
            'stats' => $stats,
        ];
    }

    /**
     * Calcula estadísticas del recorrido (distancia, tiempos, puntos).
     */
    public function calcularStats(Collection $points): array
    {
        if ($points->isEmpty()) {
            return [
                'total_puntos' => 0,
                'distancia_km' => 0,
                'hora_inicio' => null,
                'hora_fin' => null,
            ];
        }

        $distanciaM = 0;
        for ($i = 1; $i < $points->count(); $i++) {
            $prev = $points[$i - 1];
            $curr = $points[$i];
            $distanciaM += $this->haversine(
                (float) $prev->latitude, (float) $prev->longitude,
                (float) $curr->latitude, (float) $curr->longitude
            );
        }

        $inicio = $points->first()->recorded_at ?? null;
        $fin = $points->last()->recorded_at ?? null;

        return [
            'total_puntos' => $points->count(),
            'distancia_km' => round($distanciaM / 1000, 2),
            'hora_inicio' => $inicio ? Carbon::parse($inicio)->format('H:i') : null,
            'hora_fin' => $fin ? Carbon::parse($fin)->format('H:i') : null,
        ];
    }

    /**
     * Descarga el mapa estático OSM con marcadores de inicio/fin y trazado.
     */
    private function descargarMapaOsm(Collection $coords): ?string
    {
        $muestra = $this->simplificar($coords, self::MAX_POINTS_URL);

        [$centroLat, $centroLng] = $this->calcularCentro($coords);
        $zoom = $this->calcularZoom($coords);

        $proveedor = (string) config('services.route_map.provider', 'osm');
        $baseUrl = (string) config('services.route_map.osm_url', 'https://staticmap.openstreetmap.de/staticmap.php');
        $timeout = (int) config('services.route_map.timeout', 6);

        $inicio = $muestra->first();
        $fin = $muestra->last();

        // Trazado como path de puntos. staticmap.openstreetmap.de acepta hasta ~100 pares.
        $path = $muestra->map(fn ($c) => round($c[0], 5).','.round($c[1], 5))->implode('|');

        $query = http_build_query([
            'center' => round($centroLat, 5).','.round($centroLng, 5),
            'zoom' => $zoom,
            'size' => self::MAP_WIDTH.'x'.self::MAP_HEIGHT,
            // Marcador verde = inicio, rojo = fin.
            'markers' => round($inicio[0], 5).','.round($inicio[1], 5).',lightgreen1|'.round($fin[0], 5).','.round($fin[1], 5).',red1',
        ]);

        // El path se agrega sin codificar las comas/pipes para que OSM lo entienda.
        $url = $baseUrl.'?'.$query.'&path=color:0x2563ebff|weight:4|'.$path;

        // Proveedor alternativo Mapbox (requiere token) si se configura.
        if ($proveedor === 'mapbox' && config('services.route_map.mapbox_token')) {
            $url = $this->construirUrlMapbox($muestra);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'NOA-Transportes/1.0 (reporte PDF recorrido GPS)',
        ]);
        $contenido = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($contenido === false || $httpCode !== 200 || strlen($contenido) < 5000) {
            Logger::warning("RouteMapService: OSM respondió HTTP {$httpCode} (".strlen((string) $contenido).' bytes).');

            return null;
        }

        if (str_contains($contentType, 'text') || str_contains($contentType, 'json')) {
            Logger::warning('RouteMapService: OSM devolvió texto en lugar de imagen.');

            return null;
        }

        return base64_encode($contenido);
    }

    /**
     * Construye URL de Mapbox Static Images API (opcional, requiere token).
     */
    private function construirUrlMapbox(Collection $muestra): string
    {
        $token = (string) config('services.route_map.mapbox_token');
        $coordsStr = $muestra->map(fn ($c) => round($c[1], 5).','.round($c[0], 5))->implode(';');
        $inicio = $muestra->first();
        $fin = $muestra->last();

        [$centroLat, $centroLng] = $this->calcularCentro($muestra);
        $zoom = $this->calcularZoom($muestra);

        $overlays = 'pin-s-a+22c55e('.$inicio[1].','.$inicio[0].'),'
            .'pin-s-b+ef4444('.$fin[1].','.$fin[0].'),'
            .'path-4+2563eb-0.8('.$coordsStr.')';

        return 'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/'
            .$overlays.'/'
            .round($centroLng, 5).','.round($centroLat, 5).','.$zoom.',0/'
            .self::MAP_WIDTH.'x'.self::MAP_HEIGHT.'?access_token='.$token;
    }

    /**
     * Genera un PNG offline con el trazado normalizado (respaldo sin internet).
     * Usa GD para que DomPDF lo renderice siempre (el SVG en data-uri no es fiable en DomPDF).
     */
    private function generarPngRespaldo(Collection $coords): ?string
    {
        try {
            if (! extension_loaded('gd')) {
                Logger::warning('RouteMapService: GD no disponible, no se pudo generar el mapa de respaldo.');

                return null;
            }

            $w = self::MAP_WIDTH;
            $h = self::MAP_HEIGHT;
            $pad = 40;

            $lats = $coords->map(fn ($c) => $c[0]);
            $lngs = $coords->map(fn ($c) => $c[1]);
            $minLat = $lats->min();
            $maxLat = $lats->max();
            $minLng = $lngs->min();
            $maxLng = $lngs->max();

            $rangoLat = max($maxLat - $minLat, 0.0001);
            $rangoLng = max($maxLng - $minLng, 0.0001);

            $px = $coords->map(function ($c) use ($minLat, $minLng, $rangoLat, $rangoLng, $w, $h, $pad) {
                $x = (int) round($pad + (($c[1] - $minLng) / $rangoLng) * ($w - 2 * $pad));
                // Y invertido: lat mayor arriba.
                $y = (int) round($pad + (1 - (($c[0] - $minLat) / $rangoLat)) * ($h - 2 * $pad));

                return [$x, $y];
            })->values();

            $img = imagecreatetruecolor($w, $h);
            $fondo = imagecolorallocate($img, 241, 245, 249);
            $borde = imagecolorallocate($img, 148, 163, 184);
            $linea = imagecolorallocate($img, 37, 99, 235);
            $verde = imagecolorallocate($img, 34, 197, 94);
            $rojo = imagecolorallocate($img, 239, 68, 68);
            $blanco = imagecolorallocate($img, 255, 255, 255);
            $texto = imagecolorallocate($img, 51, 65, 85);
            $gris = imagecolorallocate($img, 100, 116, 139);

            imagefill($img, 0, 0, $fondo);
            imagerectangle($img, 0, 0, $w - 1, $h - 1, $borde);

            // Retícula de referencia.
            for ($i = 1; $i < 6; $i++) {
                $gx = (int) round($i * $w / 6);
                imageline($img, $gx, 0, $gx, $h, $borde);
            }
            for ($i = 1; $i < 3; $i++) {
                $gy = (int) round($i * $h / 3);
                imageline($img, 0, $gy, $w, $gy, $borde);
            }

            // Trazado del recorrido.
            imagesetthickness($img, 4);
            for ($i = 1; $i < $px->count(); $i++) {
                imageline($img, $px[$i - 1][0], $px[$i - 1][1], $px[$i][0], $px[$i][1], $linea);
            }
            imagesetthickness($img, 1);

            // Marcadores de inicio (verde) y fin (rojo) con borde blanco.
            $inicio = $px->first();
            $fin = $px->last();
            imagefilledellipse($img, $inicio[0], $inicio[1], 26, 26, $blanco);
            imagefilledellipse($img, $inicio[0], $inicio[1], 20, 20, $verde);
            imagefilledellipse($img, $fin[0], $fin[1], 26, 26, $blanco);
            imagefilledellipse($img, $fin[0], $fin[1], 20, 20, $rojo);

            imagestring($img, 5, 12, 10, 'Recorrido GPS (vista esquematica sin conexion)', $texto);
            imagestring($img, 4, 12, $h - 24, 'Verde = inicio   Rojo = fin   Azul = trazado GPS', $gris);

            ob_start();
            imagepng($img);
            $bin = ob_get_clean();
            imagedestroy($img);

            return $bin !== false && $bin !== '' ? base64_encode($bin) : null;
        } catch (\Throwable $e) {
            Logger::warning('RouteMapService: no se pudo generar el PNG de respaldo: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Reduce la cantidad de puntos por muestreo uniforme para la URL del mapa.
     */
    private function simplificar(Collection $coords, int $max): Collection
    {
        if ($coords->count() <= $max) {
            return $coords->values();
        }

        $paso = $coords->count() / $max;
        $salida = collect();
        for ($i = 0; $i < $max; $i++) {
            $salida->push($coords[(int) floor($i * $paso)]);
        }
        // Asegurar el punto final exacto.
        $salida->push($coords->last());

        return $salida->values();
    }

    private function calcularCentro(Collection $coords): array
    {
        return [
            ($coords->map(fn ($c) => $c[0])->min() + $coords->map(fn ($c) => $c[0])->max()) / 2,
            ($coords->map(fn ($c) => $c[1])->min() + $coords->map(fn ($c) => $c[1])->max()) / 2,
        ];
    }

    /**
     * Zoom aproximado según la distancia máxima del recorrido.
     */
    private function calcularZoom(Collection $coords): int
    {
        $maxDist = 0;
        $lats = $coords->map(fn ($c) => $c[0]);
        $lngs = $coords->map(fn ($c) => $c[1]);
        $maxDist = max(
            $this->haversine($lats->min(), $lngs->min(), $lats->max(), $lngs->min()),
            $this->haversine($lats->min(), $lngs->min(), $lats->min(), $lngs->max())
        );

        return match (true) {
            $maxDist < 2000 => 14,
            $maxDist < 5000 => 13,
            $maxDist < 15000 => 12,
            $maxDist < 40000 => 11,
            $maxDist < 100000 => 10,
            default => 9,
        };
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radio = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $radio * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
