<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de la hoja de control de entrega de servicios para servicios directos con la empresa.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceInternalControl extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'service_internal_controls';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'third_party_uuid',
        'fuec_uuid',
        'service_delivery_control_sheet_uuid',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el vehículo.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con el tercero (conductor).
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Relación con el FUEC.
     */
    public function fuec(): BelongsTo
    {
        return $this->belongsTo(Fuec::class, 'fuec_uuid', 'uuid');
    }

    /**
     * Relación con la hoja de control de entrega de servicios.
     */
    public function serviceDeliveryControlSheet(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryControlSheet::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }
}
