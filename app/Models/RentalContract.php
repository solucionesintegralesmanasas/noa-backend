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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para Contratos de Alquiler de Vehículos.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-11-18
 */
class RentalContract extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

    /**
     * La tabla asociada al modelo.
     */
    protected $table = 'rental_contracts';

    /**
     * Los atributos que son asignables masivamente.
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'contract_number',
        'vehicle_uuid',
        'owner_uuid',
        'start_date',
        'end_date',
        'monthly_term',
        'monthly_fee',
        'status',
        'observations',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     */
    protected $hidden = [];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'monthly_term' => 'integer',
        'monthly_fee' => 'decimal:2',
    ];

    /**
     * Relación con la empresa.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con el vehículo.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con el propietario.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_uuid', 'uuid');
    }

    /**
     * Relación con las cuotas del contrato.
     */
    public function installments()
    {
        return $this->hasMany(RentalInstallment::class, 'rental_contract_uuid', 'uuid')
            ->orderBy('installment_number');
    }

    /**
     * Relación con los descuentos registrados del contrato.
     */
    public function discounts()
    {
        return $this->hasMany(RentalDiscount::class, 'rental_contract_uuid', 'uuid')
            ->orderByDesc('discount_date')
            ->orderByDesc('id');
    }

    /**
     * Scopes para filtrado.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByVehicle($query, string $vehicleUuid)
    {
        return $query->where('vehicle_uuid', $vehicleUuid);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
