<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class EmailNotificationLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'document_uuid',
        'recipient_email',
        'milestone',
        'status',
    ];
}
