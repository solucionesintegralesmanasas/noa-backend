<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contratos laborales por empleado.
 */
class EmploymentContract extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'employment_contracts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_uuid',
        'third_party_uuid',
        'contract_type',
        'start_date',
        'end_date',
        'base_salary',
        'salary_type',
        'transport_subsidy_applies',
        'working_hours_per_week',
        'status',
        'termination_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'base_salary' => 'decimal:2',
        'working_hours_per_week' => 'decimal:2',
        'transport_subsidy_applies' => 'boolean',
    ];

    /**
     * The third party (employee) that owns the contract.
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }
}
