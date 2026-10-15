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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Resolución de facturación por empresa (rango interno en fase 1 y 2;
 * número oficial DIAN cuando se active el proveedor tecnológico).
 */
class BillingResolution extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'billing_resolutions';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'branch_uuid',
        'resolution_type_code',
        'resolution_number',
        'invoice_prefix',
        'invoice_start_number',
        'invoice_end_number',
        'last_consecutive',
        'start_date',
        'end_date',
        'billing_provider_uuid',
        'description',
        'is_active',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'invoice_start_number' => 'integer',
        'invoice_end_number' => 'integer',
        'last_consecutive' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(BillingResolutionType::class, 'resolution_type_code', 'code');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(BillingProvider::class, 'billing_provider_uuid', 'uuid');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CommercialInvoice::class, 'billing_resolution_uuid', 'uuid');
    }

    public function estaVigente(): bool
    {
        $hoy = now()->toDateString();

        return (bool) $this->is_active
            && $this->start_date->toDateString() <= $hoy
            && $this->end_date->toDateString() >= $hoy
            && $this->last_consecutive < $this->invoice_end_number;
    }
}
