<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Descripción funcional de la clase Maintenance.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class Maintenance extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'maintenance';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'maintenance_type',
        'mileage',
        'service_description',
        'mechanic_name',
        'workshop_name',
        'maintenance_date',
        'labor_cost',
        'parts_cost',
        'invoice_number',
        'next_maintenance_date',
        'notes',
        'status',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mileage' => 'integer',
        'maintenance_date' => 'date',
        'labor_cost' => 'float',
        'parts_cost' => 'float',
        'next_maintenance_date' => 'date',
    ];

    /**
     * Relación con vehicles.
     *
     * @return BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con companies.
     *
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con maintenance_parts.
     *
     * @return HasMany
     */
    public function maintenanceParts()
    {
        return $this->hasMany(MaintenancePart::class, 'maintenance_uuid', 'uuid');
    }
}
