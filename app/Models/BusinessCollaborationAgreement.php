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
 * Descripción funcional de la clase BusinessCollaborationAgreement.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class BusinessCollaborationAgreement extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'business_collaboration_agreements';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'vehicle_uuid',
        'resolution_number',
        'agreement_internal_id',
        'contracting_entity_nit',
        'contracting_entity_name',
        'effective_date',
        'expiry_date',
        'rep_name',
        'rep_document_id',
        'transport_modality',
        'max_fleet_capacity',
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
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'max_fleet_capacity' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Relación con vehicles.
     *
     * @return BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
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
}
