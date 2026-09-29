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
     * Los atributos que son asignables masivamente.
     *
     * Solo el servidor mantiene estas filas y siempre con valores calculados,
     * nunca con datos directos del cliente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'third_party_uuid',
        'service_date',
        'total_points',
        'total_distance_meters',
        'samples',
    ];

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

    /**
     * Índices a conservar al diezmar una lista ordenada al tope.
     *
     * Es la única definición del diezmado: la usan el mantenimiento diario, el
     * mapa y el backfill de la migración, así que una misma muestra siempre
     * sobrevive igual venga de donde venga. Primero y último garantizados.
     *
     * @return array<int, int>
     */
    public static function indicesParaTope(int $total, int $tope): array
    {
        if ($total <= $tope) {
            return range(0, $total - 1);
        }

        $paso = ($total - 1) / ($tope - 1);
        $indices = [];

        for ($i = 0; $i < $tope; $i++) {
            $indices[] = (int) round($i * $paso);
        }

        $indices[0] = 0;
        $indices[$tope - 1] = $total - 1;

        return array_values(array_unique($indices));
    }
}
