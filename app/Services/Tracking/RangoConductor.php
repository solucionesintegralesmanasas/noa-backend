<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use Illuminate\Support\Carbon;

/**
 * El conductor, la empresa y el rango de fechas de una consulta de historial.
 *
 * Evita que esos cuatro valores viajen sueltos por cada método privado del
 * servicio de historial.
 */
readonly class RangoConductor
{
    public function __construct(
        public string $conductorUuid,
        public ?string $empresaUuid,
        public Carbon $inicio,
        public Carbon $fin,
    ) {}
}
