<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de tanqueo de un vehículo.
 *
 * Flujo: PENDIENTE → APROBADA → USADA. También puede terminar en RECHAZADA,
 * ANULADA o VENCIDA (24 h después de la aprobación).
 */
class FuelRequest extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasUuid;
    use LogsActivity;

    protected $table = 'fuel_requests';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'driver_uuid',
        'requested_by_user_uuid',
        'code',
        'public_token',
        'status',
        'requested_at',
        'approved_by_user_uuid',
        'approved_by_name',
        'approved_at',
        'rejected_by_user_uuid',
        'rejected_at',
        'rejection_reason',
        'expires_at',
        'used_at',
        'fuel_log_uuid',
        'snapshot',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'snapshot' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'driver_uuid', 'uuid');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_uuid', 'uuid');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_uuid', 'uuid');
    }

    public function fuelLog(): BelongsTo
    {
        return $this->belongsTo(FuelLog::class, 'fuel_log_uuid', 'uuid');
    }
}
