<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo ChatContextCache que representa la tabla chat_context_cache.
 * Cache de contexto persistente para sesiones de chat por usuario/empresa
 *
 * @property int $id
 * @property string $user_uuid
 * @property string $company_uuid
 * @property array $context
 * @property string $created_at
 * @property string $updated_at
 */
class ChatContextCache extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, LogsActivity;

    protected $table = 'chat_context_cache';

    protected $fillable = [
        'user_uuid',
        'company_uuid',
        'context',
    ];

    protected $hidden = [];

    protected $casts = [
        'context' => 'array',
    ];
}
