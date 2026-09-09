<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Descripción funcional de la clase DriverLicense.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class DriverLicense extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'driver_licenses';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'third_party_uuid',
        'number',
        'category',
        'issue_date',
        'expiration_date',
        'restrictions',
        'status',
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
        'issue_date' => 'date',
        'expiration_date' => 'date',
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

    /**
     * Relación con companies.
     *
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con owners_drivers (Conductor de propietario).
     *
     * @return HasOne
     */
    public function ownerDriver()
    {
        return $this->hasOne(OwnerDriver::class, 'driver_license_uuid', 'uuid');
    }
}
