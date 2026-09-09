<?php

declare(strict_types=1);

namespace App\Traits;

use DateTimeInterface;

/**
 * Trait FormatsDates
 *
 * Sobrescribe la serialización de fechas de Eloquent para que la API
 * devuelva fechas legibles (Y-m-d H:i:s) en lugar del formato ISO con 'T' y 'Z'.
 *
 * USO EN EL MODELO:
 *
 * use App\Traits\FormatsDates;
 *
 * class Invoice extends Model
 * {
 *     use FormatsDates;
 * }
 */
trait FormatsDates
{
    /**
     * Prepara la fecha para ser enviada en la respuesta JSON de la API.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        // En lugar de '2026-05-13T20:00:00.000000Z' devolverá '2026-05-13 20:00:00'
        return $date->format('Y-m-d H:i:s');
    }
}
