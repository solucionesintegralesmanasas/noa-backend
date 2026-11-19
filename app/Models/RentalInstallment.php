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
 * Modelo para Cuotas de Contratos de Alquiler de Vehículos.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-11-18
 */
class RentalInstallment extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     */
    protected $table = 'rental_installments';

    /**
     * Los atributos que son asignables masivamente.
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'rental_contract_uuid',
        'installment_number',
        'due_date',
        'gross_amount',
        'previous_balance',
        'discount_amount',
        'net_amount',
        'paid_amount',
        'pending_balance',
        'status',
        'payment_date',
        'notes',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     */
    protected $hidden = [];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'gross_amount' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_balance' => 'decimal:2',
    ];

    /**
     * Relación con el contrato.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'rental_contract_uuid', 'uuid');
    }

    /**
     * Descuentos aplicados a esta cuota.
     */
    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RentalDiscount::class, 'rental_installment_uuid', 'uuid');
    }
}
