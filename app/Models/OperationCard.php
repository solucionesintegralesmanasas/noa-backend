<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Descripción funcional de la clase OperationCard.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class OperationCard extends Model
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
    protected $table = 'operation_cards';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'affiliated_company',
        'area_of_coverage',
        'service_type',
        'transport_mode',
        'issue_date',
        'expiration_date',
        'operating_card_number',
        'operation_card_type',
        'runt_status',
        'procedure_uuid',
        'cancelled_at',
        'cancellation_reason',
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
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'cancelled_at' => 'datetime',
        'status' => 'boolean',
    ];

    /** Tipos de tarjeta que consumen cupo al emitirse (exigen movimiento de descuento previo). */
    public const TIPOS_QUE_CONSUMEN_CUPO = ['NUEVA', 'CAMBIO_DE_EMPRESA'];

    /** Tipos de tarjeta que no mueven el contador de cupos (el cupo ya estaba ocupado). */
    public const TIPOS_QUE_NO_MUEVEN_CUPO = ['RENOVACION', 'DUPLICADO', 'MODIFICACION'];

    /** Indica si el tipo de tarjeta consume cupo de capacidad transportadora. */
    public static function tipoConsumeCupo(?string $tipo): bool
    {
        return in_array(strtoupper((string) $tipo), self::TIPOS_QUE_CONSUMEN_CUPO, true);
    }

    /** Si la tarjeta está vigente frente al RUNT. */
    public function esActiva(): bool
    {
        return $this->runt_status === 'ACTIVA' && (bool) $this->status;
    }

    /** Movimientos de cupo vinculados a esta tarjeta. */
    public function capacityMovements(): HasMany
    {
        return $this->hasMany(CapacityMovement::class, 'operation_card_uuid', 'uuid');
    }

    /** Expediente de radicación que originó la tarjeta. */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_uuid', 'uuid');
    }

    /** Tarjetas vigentes (no canceladas). */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('runt_status', 'ACTIVA');
    }

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
}
