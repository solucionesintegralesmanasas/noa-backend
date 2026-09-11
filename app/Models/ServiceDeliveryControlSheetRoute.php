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
 * Recorrido perteneciente a una planilla de control de prestación de servicios.
 * Una planilla puede tener N recorridos definidos por el conductor.
 *
 * @author Darwin Montes
 * @version 1.0.0
 * @since 1.0.0
 * @created 2026-09-11
 */
class ServiceDeliveryControlSheetRoute extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'service_delivery_control_sheet_routes';

    protected $fillable = [
        'uuid',
        'service_delivery_control_sheet_uuid',
        'order_index',
        'origin',
        'destination',
        'end_time',
        'ending_kilometer',
        'number_of_tolls',
        'total_toll_value',
        'end_novelty',
        'is_active',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'order_index' => 'integer',
        'end_time' => 'datetime:H:i:s',
        'ending_kilometer' => 'decimal:2',
        'number_of_tolls' => 'integer',
        'total_toll_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function serviceDeliveryControlSheet(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryControlSheet::class, 'service_delivery_control_sheet_uuid', 'uuid');
    }
}
