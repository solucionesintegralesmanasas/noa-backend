<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractSignature extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $table = 'contract_signatures';

    protected $fillable = [
        'uuid', 'company_uuid', 'contract_origin', 'contract_uuid', 'signer_role',
        'signer_name', 'signer_document', 'signer_email', 'signer_phone',
        'token_hash', 'expires_at', 'signed_at', 'ip_address', 'document_hash',
        'signature_data', 'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime', 'signed_at' => 'datetime',
    ];
}
