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
 * Modelo DriverLocationAlert para alertas de geolocalización.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 *
 * @created 2026-09-10
 */
class DriverLocationAlert extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'driver_location_alerts';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'third_party_uuid',
        'driver_location_uuid',
        'alert_type',
        'geofence_uuid',
        'message',
        'latitude',
        'longitude',
        'is_read',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'alert_type' => 'string',
        'is_read' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
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
     * Obtiene la ubicación de la alerta.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(DriverLocation::class, 'driver_location_uuid', 'uuid');
    }
}