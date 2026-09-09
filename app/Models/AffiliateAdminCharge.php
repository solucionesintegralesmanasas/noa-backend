<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Representa los cobros y cargos administrativos aplicados a los vehículos afiliados.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-31
 */
class AffiliateAdminCharge extends Model
{
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'affiliate_admin_charges';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'payment_reference',
        'vehicle_uuid',
        'charge_type',
        'payment_method',
        'concept',
        'amount',
        'currency_code',
        'period_date',
        'due_date',
        'next_payment_date',
        'late_fee_percentage',
        'status',
        'payment_date',
        'bank_reference',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'late_fee_percentage' => 'decimal:2',
            'period_date' => 'date',
            'due_date' => 'date',
            'next_payment_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    /**
     * La empresa (tenant) asociada al cargo administrativo.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * El vehículo afiliado asociado al cargo administrativo.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }
}
