<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo entre un conductor (tercero) y los afiliados que pueden verlo y
 * operarlo. El conductor sigue siendo un único `third_parties` de la empresa;
 * `is_primary` identifica a su propietario principal.
 */
class AffiliateDriver extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasUuid;
    use LogsActivity;

    protected $table = 'affiliate_drivers';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'driver_uuid',
        'affiliate_uuid',
        'is_primary',
        'is_active',
        'created_by_uuid',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'driver_uuid', 'uuid');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'affiliate_uuid', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
