<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RuntTxtBatch extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $table = 'runt_txt_batches';

    protected $fillable = [
        'uuid', 'company_uuid', 'procedure_uuid', 'origin', 'contract_uuid',
        'sequence', 'content', 'content_hash', 'runt_response', 'status',
    ];
}
