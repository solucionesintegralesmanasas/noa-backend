<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo DriverLocationDailyStat: resumen diario del recorrido de un conductor.
 *
 * Es un derivado de los puntos GPS para que el mapa no escanee el histórico:
 * el mantenimiento lo hace el servidor al recibir cada punto, nunca el cliente
 * (por eso no hay nada asignable en masa) y sin registro de actividad, porque
 * se reescribe en cada punto recibido.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 *
 * @created 2026-09-29
 */
class DriverLocationDailyStat extends Model
{
    use HasFactory, HasUuid;

    /**
     * Muestras máximas que guarda cada día. El mapa pide como máximo 5000
     * puntos y el rango máximo es de 31 días: con 300 por día siempre alcanza.
     */
    public const MUESTRAS_POR_DIA = 300;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'driver_location_daily_stats';

    /**
     * Ningún atributo es asignable en masa: las filas las mantiene el servidor.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'service_date' => 'date',
        'total_points' => 'integer',
        'total_distance_meters' => 'decimal:2',
        'samples' => 'array',
    ];
}
