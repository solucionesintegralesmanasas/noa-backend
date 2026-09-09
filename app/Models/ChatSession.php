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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo ChatSession que representa la tabla chat_sessions.
 * Sesiones de chat por usuario/empresa
 *
 * @property int $id
 * @property string $uuid
 * @property string $user_uuid
 * @property string|null $company_uuid
 * @property string|null $title
 * @property string|null $module
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 */
class ChatSession extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    protected $table = 'chat_sessions';

    protected $fillable = [
        'uuid',
        'user_uuid',
        'company_uuid',
        'title',
        'module',
        'status',
        'meta',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }
}
