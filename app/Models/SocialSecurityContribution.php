<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descripción funcional de la clase SocialSecurityContribution.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class SocialSecurityContribution extends Model
{
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'social_security_contributions';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'third_party_uuid',
        'billing_period',
        'pila_pin',
        'contribution_type',
        'ibc_amount',
        'health_paid',
        'pension_paid',
        'risk_labor_paid',
        'compensation_fund_paid',
        'eps_name',
        'eps_affiliation_date',
        'pension_name',
        'pension_affiliation_date',
        'risk_labor_name',
        'risk_labor_affiliation_date',
        'compensation_fund_name',
        'compensation_fund_affiliation_date',
        'payment_date',
        'status',
        'notes',
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
        'billing_period' => 'date',
        'ibc_amount' => 'float',
        'health_paid' => 'boolean',
        'pension_paid' => 'boolean',
        'risk_labor_paid' => 'boolean',
        'compensation_fund_paid' => 'boolean',
        'eps_affiliation_date' => 'date',
        'pension_affiliation_date' => 'date',
        'risk_labor_affiliation_date' => 'date',
        'compensation_fund_affiliation_date' => 'date',
        'payment_date' => 'date',
    ];

    /**
     * Relación con third_parties.
     *
     * @return BelongsTo
     */
    public function thirdParty()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }
}
