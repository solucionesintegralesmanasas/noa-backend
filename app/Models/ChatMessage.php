<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ChatMessage que representa la tabla chat_messages.
 * Mensajes individuales dentro de una sesión de chat
 *
 * @property int $id
 * @property string $uuid
 * @property string $company_uuid
 * @property int $session_id
 * @property string $role
 * @property string $content
 * @property int|null $tokens_used
 * @property float|null $response_time
 * @property int|null $feedback
 * @property array|null $metadata
 * @property string $created_at
 * @property string $updated_at
 */
class ChatMessage extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'chat_messages';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'session_id',
        'role',
        'content',
        'tokens_used',
        'response_time',
        'feedback',
        'metadata',
    ];

    protected $hidden = [];

    protected $casts = [
        'metadata' => 'array',
        'tokens_used' => 'integer',
        'response_time' => 'float',
        'feedback' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
