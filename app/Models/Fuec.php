<?php

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
 * Descripción funcional de la clase Fuec.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-01-20
 */
class Fuec extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * @var string
     */
    protected $table = 'fuec';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'issue_date',
        'request_number',
        'number_fuec',
        'contract_number_display',
        'company_uuid',
        'contractor_uuid',
        'vehicle_uuid',
        'effective_date',
        'expiration_date',
        'origin_route',
        'destination_route',
        'object_contract_uuid',
        'main_conductor_uuid',
        'secondary_conductor_uuid',
        'tertiary_conductor_uuid',
        'verification_code',
        'status',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'issue_date' => 'date',
        'effective_date' => 'date',
        'expiration_date' => 'date',
    ];

    /**
     * El contratista asociado a este FUEC
     *
     * @return BelongsTo
     */
    public function contractor()
    {
        return $this->belongsTo(Contractor::class, 'contractor_uuid', 'uuid');
    }

    /**
     * El vehículo asociado a este FUEC
     *
     * @return BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * El objeto de contrato asociado a este FUEC
     *
     * @return BelongsTo
     */
    public function objectContract()
    {
        return $this->belongsTo(ObjectsContract::class, 'object_contract_uuid', 'uuid');
    }

    /**
     * El objeto de contrato asociado a este FUEC (Plural alias para compatibilidad)
     *
     * @return BelongsTo
     */
    public function objectsContract()
    {
        return $this->belongsTo(ObjectsContract::class, 'object_contract_uuid', 'uuid');
    }

    /**
     * El objeto de contrato asociado a este FUEC (alias snake_case)
     *
     * @return BelongsTo
     */
    public function objects_contract()
    {
        return $this->objectsContract();
    }

    /**
     * El conductor principal asociado a este FUEC
     *
     * @return BelongsTo
     */
    public function mainConductor()
    {
        return $this->belongsTo(ThirdParty::class, 'main_conductor_uuid', 'uuid');
    }

    /**
     * El conductor principal asociado a este FUEC (alias snake_case)
     *
     * @return BelongsTo
     */
    public function main_conductor()
    {
        return $this->mainConductor();
    }

    /**
     * El conductor secundario asociado a este FUEC
     *
     * @return BelongsTo
     */
    public function secondaryConductor()
    {
        return $this->belongsTo(ThirdParty::class, 'secondary_conductor_uuid', 'uuid');
    }

    /**
     * El conductor secundario asociado a este FUEC (alias snake_case)
     *
     * @return BelongsTo
     */
    public function secondary_conductor()
    {
        return $this->secondaryConductor();
    }

    /**
     * El conductor terciario asociado a este FUEC
     *
     * @return BelongsTo
     */
    public function tertiaryConductor()
    {
        return $this->belongsTo(ThirdParty::class, 'tertiary_conductor_uuid', 'uuid');
    }

    /**
     * El conductor terciario asociado a este FUEC (alias snake_case)
     *
     * @return BelongsTo
     */
    public function tertiary_conductor()
    {
        return $this->tertiaryConductor();
    }

    /**
     * Los pasajeros que pertenecen a este FUEC
     *
     * @return HasMany
     */
    public function passengers()
    {
        return $this->hasMany(FuecPassenger::class, 'fuec_uuid', 'uuid');
    }
}
