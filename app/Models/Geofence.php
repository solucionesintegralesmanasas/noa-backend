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
 * Modelo Geofence para zonas geográficas.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 *
 * @created 2026-09-10
 */
class Geofence extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'geofences';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'name',
        'description',
        'type',
        'center_lat',
        'center_lng',
        'radius_meters',
        'polygon_points',
        'alert_on_enter',
        'alert_on_exit',
        'max_speed_kmh',
        'is_active',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string',
        'alert_on_enter' => 'boolean',
        'alert_on_exit' => 'boolean',
        'is_active' => 'boolean',
        'center_lat' => 'decimal:8',
        'center_lng' => 'decimal:8',
        'radius_meters' => 'integer',
        'polygon_points' => 'array',
    ];

    /**
     * Obtiene la empresa propietaria de la geocerca.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}