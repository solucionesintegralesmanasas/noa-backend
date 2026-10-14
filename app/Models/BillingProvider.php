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
 * Proveedor tecnológico de facturación electrónica (fase posterior).
 *
 * En fase 1 y 2 solo se registra el catálogo; ningún documento se transmite.
 */
class BillingProvider extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'billing_providers';

    protected $fillable = [
        'uuid',
        'name',
        'server_url',
        'client_id',
        'client_secret',
        'email',
        'start_date',
        'end_date',
        'company_uuid',
        'description',
        'is_active',
    ];

    protected $hidden = ['id', 'client_secret'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function resolutions(): HasMany
    {
        return $this->hasMany(BillingResolution::class, 'billing_provider_uuid', 'uuid');
    }
}
