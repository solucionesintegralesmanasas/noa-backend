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
 * Modelo para Descuentos aplicados a cuotas de Contratos de Alquiler.
 */
class RentalDiscount extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    protected $table = 'rental_discounts';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'rental_contract_uuid',
        'rental_installment_uuid',
        'discount_date',
        'concept',
        'amount',
        'observations',
    ];

    protected $casts = [
        'discount_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'rental_contract_uuid', 'uuid');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(RentalInstallment::class, 'rental_installment_uuid', 'uuid');
    }
}
