<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProvisionContract extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'service_provision_contracts';

    protected $fillable = [
        'uuid', 'company_uuid', 'procedure_uuid', 'vehicle_uuid', 'third_party_uuid',
        'contract_number', 'issue_date', 'start_date', 'end_date', 'duration',
        'valuation_amount', 'coverage', 'object_description', 'status', 'document_hash',
    ];

    protected $casts = [
        'issue_date' => 'date', 'start_date' => 'date', 'end_date' => 'date',
    ];

    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_uuid', 'uuid');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }
}
