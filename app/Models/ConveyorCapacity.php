<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ConveyorCapacity que representa la tabla conveyor_capacity.
 * Capacidad de transporte autorizada y actual
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ConveyorCapacity extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'conveyor_capacity';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'enabling_resolution_uuid',
        'vehicle_type',
        'authorized_capacity',
        'current_capacity',
        'minimum_own_capacity',
        'status',
    ];

    /**
     * Atributos que deben ser ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
    ];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Relación con EnablingResolution.
     *
     * @return BelongsTo
     */
    public function enablingResolution()
    {
        return $this->belongsTo(EnablingResolution::class, 'enabling_resolution_uuid', 'uuid');
    }
}
