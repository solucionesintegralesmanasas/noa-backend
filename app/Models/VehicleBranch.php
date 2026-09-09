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
 * Modelo para las sucursales vinculadas a los vehículos.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-11
 */
class VehicleBranch extends Model
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
    protected $table = 'vehicles_branches';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'branch_uuid',
        'entry_date',
        'exit_date',
        'exit_type',
        'is_active',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_date' => 'date',
        'exit_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con companies.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con vehicles.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con branches.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_uuid', 'uuid');
    }
}
