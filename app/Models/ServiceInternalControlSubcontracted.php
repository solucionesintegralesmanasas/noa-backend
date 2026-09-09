<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de la hoja de control de entrega de servicios para servicios subcontratados.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceInternalControlSubcontracted extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'service_internal_controls_subcontracted';

    protected $fillable = [
        'uuid',
        'vehicle_class_uuid',
        'service_delivery_control_sheet_uuid',
        'vehicle_license_plate',
        'driver_name_and_surname',
        'driver_license_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con la clase de vehículo.
     */
    public function vehicleClass(): BelongsTo
    {
        return $this->belongsTo(VehicleClass::class, 'vehicle_class_uuid', 'uuid');
    }

    /**
     * Relación con la hoja de control de entrega de servicios.
     */
    public function serviceDeliveryControlSheet(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryControlSheet::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }
}
