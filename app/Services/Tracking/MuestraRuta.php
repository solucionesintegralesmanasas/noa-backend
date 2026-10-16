<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\DriverLocation;
use Illuminate\Support\Carbon;

/**
 * Un punto de muestra del recorrido para el mapa.
 *
 * Es la única definición de la forma [latitud, longitud, fecha, velocidad] que
 * se guarda en el resumen diario: evita los índices mágicos [0..3] repartidos
 * por los servicios.
 */
readonly class MuestraRuta
{
    public function __construct(
        public float $latitud,
        public float $longitud,
        public string $registradaEn,
        public float $velocidad,
    ) {}

    public static function desdePunto(DriverLocation $punto): self
    {
        return new self(
            (float) $punto->latitude,
            (float) $punto->longitude,
            Carbon::parse($punto->recorded_at)->format('Y-m-d H:i:s'),
            (float) ($punto->speed ?? 0),
        );
    }

    /**
     * @param  array{0: float, 1: float, 2: string, 3?: float}  $arreglo
     */
    public static function desdeArreglo(array $arreglo): self
    {
        return new self(
            (float) $arreglo[0],
            (float) $arreglo[1],
            (string) $arreglo[2],
            (float) ($arreglo[3] ?? 0),
        );
    }

    /**
     * @return array{0: float, 1: float, 2: string, 3: float}
     */
    public function aArreglo(): array
    {
        return [$this->latitud, $this->longitud, $this->registradaEn, $this->velocidad];
    }

    /**
     * @return array{latitude: float, longitude: float, recorded_at: string, speed: float}
     */
    public function aRespuesta(): array
    {
        return [
            'latitude' => $this->latitud,
            'longitude' => $this->longitud,
            'recorded_at' => $this->registradaEn,
            'speed' => $this->velocidad,
        ];
    }
}
