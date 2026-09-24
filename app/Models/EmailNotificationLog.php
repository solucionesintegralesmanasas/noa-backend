<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class EmailNotificationLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'company_uuid',
        'entity_type',
        'entity_uuid',
        'recipient_email',
        'milestone',
        'sent_date',
        'status',
    ];

    protected $casts = [
        'sent_date' => 'date',
    ];
}
