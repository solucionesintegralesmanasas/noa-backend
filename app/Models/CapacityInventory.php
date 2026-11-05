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
 * Modelo para Inventario de capacidad.
 *
 * DEPRECADO (2026-11-05): nadie escribe esta tabla. El saldo real de cupos vive
 * en `conveyor_capacity.current_capacity` y su historial en `capacity_movements`
 * (App\Services\Fleet\CapacityMovementService). Se conserva solo por los datos
 * históricos; no usar en código nuevo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class CapacityInventory extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'capacity_inventory';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'conveyor_capacity_uuid',
        'procedure_uuid',
        'used_conveyor_capacity',
        'used_operating_cards',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Relación con la empresa.
     *
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con el procedimiento.
     *
     * @return BelongsTo
     */
    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_uuid', 'uuid');
    }
}
