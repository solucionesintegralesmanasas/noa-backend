<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descripción funcional de la clase InspectionResult.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class InspectionResult extends Model
{
    use FormatsDates;
    use HasFactory;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'inspection_results';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inspection_uuid',
        'item_uuid',
        'is_selected',
        'status',
        'observations',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_selected' => 'boolean',
    ];

    /**
     * Relación con vehicle_inspections.
     *
     * @return BelongsTo
     */
    public function inspection()
    {
        return $this->belongsTo(VehicleInspection::class, 'inspection_uuid', 'uuid');
    }

    /**
     * Relación con inspection_items.
     *
     * @return BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(InspectionItem::class, 'item_uuid', 'uuid');
    }
}
