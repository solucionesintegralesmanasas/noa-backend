<?php

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Preguntas contextuales para el asistente virtual.
 *
 * @author   NoaTrasporteApi
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-15
 */
class ContextualQuestion extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /** @var string */
    protected $table = 'contextual_questions';

    /** @var array<int, string> */
    protected $fillable = [
        'uuid',
        'module',
        'title',
        'question_text',
        'patterns',
        'response_type',
        'response_template',
        'entity_type',
        'filters',
        'expiring_days',
        'sort_order',
        'is_active',
    ];

    /** @var array<int, string> */
    protected $hidden = [];

    /** @var array<string, string> */
    protected $casts = [
        'patterns' => 'array',
        'filters' => 'array',
        'expiring_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
