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
 * Descripción funcional de la clase Contractor.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-01-20
 */
class Contractor extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * @var string
     */
    protected $table = 'contractors';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'document_type_uuid',
        'document_number',
        'company_name',
        'address',
        'telephone',
        'contract_number',
        'contracting_party_city',
        'vehicle_uuid',
        'responsible_name',
        'responsible_document',
        'responsible_phone',
        'responsible_address',
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
        'status' => 'boolean',
    ];

    /**
     * El tipo de documento asociado a este contratista
     *
     * @return BelongsTo
     */
    public function typeOfDocument()
    {
        return $this->belongsTo(TypeOfDocument::class, 'document_type_uuid', 'uuid');
    }

    /**
     * El vehículo asociado a este contratista
     *
     * @return BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Los Fuecs que pertenecen a este contratista
     *
     * @return HasMany
     */
    public function fuecs()
    {
        return $this->hasMany(Fuec::class, 'contractor_uuid', 'uuid');
    }
}
