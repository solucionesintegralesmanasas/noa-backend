<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descripción funcional de la clase MaintenancePart.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class MaintenancePart extends Model
{
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'maintenance_parts';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'maintenance_uuid',
        'part_name',
        'part_code',
        'quantity',
        'unit_cost',
        'supplier_uuid',
        'notes',
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
        'quantity' => 'float',
        'unit_cost' => 'float',
    ];

    /**
     * Relación con maintenance.
     *
     * @return BelongsTo
     */
    public function maintenance()
    {
        return $this->belongsTo(Maintenance::class, 'maintenance_uuid', 'uuid');
    }

    /**
     * Relación con third_parties.
     *
     * @return BelongsTo
     */
    public function supplier()
    {
        return $this->belongsTo(ThirdParty::class, 'supplier_uuid', 'uuid');
    }
}
