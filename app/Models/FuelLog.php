<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de Control de Combustible, Kilometraje y Saldos de un vehículo.
 *
 * Cada fila representa el surtimiento/consumo de un vehículo en una fecha.
 * Los campos calculados (recorrido, gallons_pumped, gallons_consumed,
 * final_balance, km_per_gallon, cost_per_km) los fija FuelLogService.
 */
class FuelLog extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    protected $table = 'fuel_logs';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'driver_uuid',
        'engine_displacement',
        'idle_hours',
        'idle_factor',
        'displacement_liters',
        'date',
        'initial_odometer',
        'final_odometer',
        'recorrido',
        'total_spent',
        'fuel_price_per_gallon',
        'gallons_pumped',
        'initial_balance',
        'gallons_consumed',
        'final_balance',
        'average_performance',
        'performance_source',
        'rendimiento',
        'km_per_gallon',
        'estimated_range_km',
        'cost_per_km',
    ];

    protected $hidden = [];

    /** Campos derivados que se agregan a la respuesta (no son columnas). */
    protected $appends = [
        'range_from_balance_km',
    ];

    protected $casts = [
        'date' => 'date',
        'initial_odometer' => 'integer',
        'final_odometer' => 'integer',
        'recorrido' => 'integer',
        'idle_hours' => 'float',
        'idle_factor' => 'float',
        'displacement_liters' => 'float',
        'total_spent' => 'float',
        'fuel_price_per_gallon' => 'float',
        'gallons_pumped' => 'float',
        'initial_balance' => 'float',
        'gallons_consumed' => 'float',
        'final_balance' => 'float',
        'average_performance' => 'float',
        'rendimiento' => 'float',
        'km_per_gallon' => 'float',
        'estimated_range_km' => 'float',
        'cost_per_km' => 'float',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'driver_uuid', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Kilómetros que alcanza el saldo de tanque final con el rendimiento aplicado.
     */
    public function getRangeFromBalanceKmAttribute(): ?float
    {
        $rendimiento = (float) ($this->average_performance ?? 0);
        if ($rendimiento <= 0) {
            return null;
        }

        return round((float) $this->final_balance * $rendimiento, 2);
    }
}
