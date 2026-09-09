<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para Contratos de gestión de flota.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class FleetServiceContract extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fleet_service_contracts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'procedure_uuid',
        'item',
        'type_of_action',
        'issue_date',
        'start_date',
        'end_date',
        'duration',
        'contract_type',
        'contract_number',
        'signature_validation',
        'valuation_amount',
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
        'issue_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
