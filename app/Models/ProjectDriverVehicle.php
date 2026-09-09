<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación pivote conductor → vehículo dentro de un proyecto (relación 1:1).
 *
 * Cada conductor asignado a un proyecto tiene exactamente UN vehículo.
 * El campo is_active permite "retirar" la asignación sin borrarla (historial).
 *
 * @author   Darwin Montes
 * @version  1.0.0
 * @since    1.0.0
 * @created  2026-09-01
 */
class ProjectDriverVehicle extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'project_driver_vehicles';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'project_uuid',
        'third_party_uuid',
        'vehicle_uuid',
        'is_active',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Los atributos que deben ser casteados.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope para obtener solo las asignaciones activas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    /**
     * Obtiene el proyecto asociado.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_uuid', 'uuid');
    }

    /**
     * Obtiene el conductor (tercero) asociado.
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Alias snake_case para la relación con el tercero.
     */
    public function third_party(): BelongsTo
    {
        return $this->thirdParty();
    }

    /**
     * Obtiene el vehículo asociado.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }
}