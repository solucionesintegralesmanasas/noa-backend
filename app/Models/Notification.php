<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Notification
 *
 * Modelo para la persistencia y gestión de alertas/notificaciones internas del sistema.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 */
class Notification extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'type',
        'title',
        'message',
        'status',
        'entity_uuid',
        'entity_type',
        'days_left',
        'expiry_date',
        'extra_data',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'days_left' => 'integer',
        'expiry_date' => 'date',
        'extra_data' => 'array',
    ];
}
