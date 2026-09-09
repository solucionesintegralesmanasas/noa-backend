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
 * Modelo para Trámites.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class Procedure extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'procedures';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'link_type',
        'company_uuid',
        'third_party_uuid',
        'vehicle_uuid',
        'procedure_code',
        'filed_number',
        'procedure_type',
        'date_of_creation',
        'city_uuid',
        'subject',
        'territorial_director_uuid',
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date_of_creation' => 'date',
    ];

    /**
     * Relación con la ciudad.
     *
     * @return BelongsTo
     */
    public function city()
    {
        return $this->belongsTo(City::class, 'city_uuid', 'uuid');
    }

    /**
     * Relación con el director territorial.
     *
     * @return BelongsTo
     */
    public function territorialDirector()
    {
        return $this->belongsTo(TerritorialDirector::class, 'territorial_director_uuid', 'uuid');
    }

    /**
     * Relación con la empresa.
     *
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con el tercero.
     *
     * @return BelongsTo
     */
    public function thirdParty()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Relación con el vehículo.
     *
     * @return BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }
}
