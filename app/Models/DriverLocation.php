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
 * Modelo DriverLocation para tracking GPS de conductores.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 *
 * @created 2026-09-10
 */
class DriverLocation extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'driver_locations';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'third_party_uuid',
        'vehicle_uuid',
        'project_uuid',
        'latitude',
        'longitude',
        'altitude',
        'speed',
        'heading',
        'accuracy',
        'battery_level',
        'is_moving',
        'source',
        'recorded_at',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'altitude' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'battery_level' => 'integer',
        'is_moving' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    /**
     * Obtiene la empresa del conductor.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Obtiene el conductor (tercero con is_driver=true).
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid')->where('is_driver', 1);
    }

    /**
     * Obtiene el vehículo asignado.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Obtiene el proyecto activo.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_uuid', 'uuid');
    }
}