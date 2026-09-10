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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hoja de control de entrega de servicios.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2026-06-19
 */
class ServiceDeliveryControlSheet extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'service_delivery_control_sheet';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'project_uuid',
        'official_name_and_surname',
        'service_date',
        'start_date',
        'end_date',
        'parent_uuid',
        'daily_route',
        'start_time',
        'end_time',
        'total_hours',
        'starting_kilometer',
        'ending_kilometer',
        'number_of_tolls',
        'total_toll_value',
        'type_of_control_sheet',
        'is_active',
    ];

    protected $casts = [
        'service_date' => 'date:Y-m-d',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'total_hours' => 'datetime:H:i:s',
        'number_of_tolls' => 'integer',
        'total_toll_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['vehicle_license_plate', 'driver_name'];

    protected $with = ['project:id,uuid,project_name,start_date,completion_date', 'routes', 'internalControl.vehicle', 'internalControl.thirdParty', 'subcontractedControl.vehicleClass'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_uuid', 'uuid');
    }

    public function internalControl(): HasOne
    {
        return $this->hasOne(ServiceInternalControl::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }

    public function subcontractedControl(): HasOne
    {
        return $this->hasOne(ServiceInternalControlSubcontracted::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryControlSheet::class, 'parent_uuid', 'uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ServiceDeliveryControlSheet::class, 'parent_uuid', 'uuid');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(ServiceDeliveryControlSheetRoute::class, 'service_delivery_control_sheet_uuid', 'uuid')->orderBy('order_index');
    }

    public function getVehicleLicensePlateAttribute(): ?string
    {
        if ($this->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA') {
            return $this->internalControl && $this->internalControl->vehicle ? $this->internalControl->vehicle->vehicle_license_plate : null;
        }

        if (in_array($this->type_of_control_sheet, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true)) {
            if ($this->subcontractedControl) {
                return $this->subcontractedControl->vehicle_license_plate;
            }
            // Fallback: vehículo externo de plataforma guardado como interno
            return $this->internalControl && $this->internalControl->vehicle ? $this->internalControl->vehicle->vehicle_license_plate : null;
        }

        return null;
    }

    public function getDriverNameAttribute(): ?string
    {
        if ($this->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA') {
            $driver = $this->internalControl ? $this->internalControl->thirdParty : null;
            if ($driver) {
                return trim($driver->first_name.' '.$driver->last_name);
            }
        }

        if (in_array($this->type_of_control_sheet, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true)) {
            if ($this->subcontractedControl) {
                return $this->subcontractedControl->driver_name_and_surname;
            }
            $driver = $this->internalControl ? $this->internalControl->thirdParty : null;
            if ($driver) {
                return trim($driver->first_name.' '.$driver->last_name);
            }
        }

        return null;
    }
}
