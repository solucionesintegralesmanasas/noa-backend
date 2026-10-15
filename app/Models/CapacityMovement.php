<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de cupo de capacidad transportadora (libro mayor).
 *
 * Registra cada descuento o liberación de cupo con su saldo antes/después para
 * poder auditar por qué una empresa tiene los cupos que tiene (ley del
 * Ministerio de Transporte: el cupo se descuenta al aprobar la capacidad de un
 * vehículo nuevo o el ingreso por cambio de empresa, y se libera al quedar en
 * firme una desvinculación o una salida).
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-10-15
 */
class CapacityMovement extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    public const DESCUENTO_NUEVO = 'DESCUENTO_NUEVO';

    public const DESCUENTO_INGRESO = 'DESCUENTO_INGRESO';

    public const LIBERACION_DESVINCULACION = 'LIBERACION_DESVINCULACION';

    public const LIBERACION_SALIDA = 'LIBERACION_SALIDA';

    public const AJUSTE_MANUAL = 'AJUSTE_MANUAL';

    /** Tipos que descuentan un cupo. */
    public const TIPOS_DESCUENTO = [self::DESCUENTO_NUEVO, self::DESCUENTO_INGRESO];

    /** Tipos que liberan un cupo. */
    public const TIPOS_LIBERACION = [self::LIBERACION_DESVINCULACION, self::LIBERACION_SALIDA];

    protected $table = 'capacity_movements';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'conveyor_capacity_uuid',
        'vehicle_uuid',
        'vehicle_type',
        'procedure_uuid',
        'operation_card_uuid',
        'movement_type',
        'quantity',
        'capacity_before',
        'capacity_after',
        'applied',
        'resolution_number',
        'notes',
        'created_by',
    ];

    protected $hidden = [];

    protected $casts = [
        'quantity' => 'integer',
        'capacity_before' => 'integer',
        'capacity_after' => 'integer',
        'applied' => 'boolean',
    ];

    /** Si el movimiento descuenta un cupo. */
    public function esDescuento(): bool
    {
        return in_array($this->movement_type, self::TIPOS_DESCUENTO, true);
    }

    /** Si el movimiento libera un cupo. */
    public function esLiberacion(): bool
    {
        return in_array($this->movement_type, self::TIPOS_LIBERACION, true);
    }

    /** Signo del movimiento sobre el saldo: −1 descuenta, +1 libera, 0 no aplica. */
    public static function signo(string $tipo): int
    {
        if (in_array($tipo, self::TIPOS_DESCUENTO, true)) {
            return -1;
        }

        return in_array($tipo, self::TIPOS_LIBERACION, true) ? 1 : 0;
    }

    public function conveyorCapacity(): BelongsTo
    {
        return $this->belongsTo(ConveyorCapacity::class, 'conveyor_capacity_uuid', 'uuid');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_uuid', 'uuid');
    }

    public function operationCard(): BelongsTo
    {
        return $this->belongsTo(OperationCard::class, 'operation_card_uuid', 'uuid');
    }

    public function scopeDeEmpresa(Builder $query, string $companyUuid): Builder
    {
        return $query->where('company_uuid', $companyUuid);
    }

    public function scopeDeVehiculo(Builder $query, string $vehicleUuid): Builder
    {
        return $query->where('vehicle_uuid', $vehicleUuid);
    }

    public function scopeNoVinculados(Builder $query): Builder
    {
        return $query->whereNull('operation_card_uuid');
    }
}
