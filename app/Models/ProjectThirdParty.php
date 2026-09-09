<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación pivote entre un proyecto y un conductor (tercero).
 *
 * @author   Darwin Montes
 * @version  1.0.0
 * @since    1.0.0
 * @created  2026-09-01
 */
class ProjectThirdParty extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'project_third_parties';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'project_uuid',
        'third_party_uuid',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Obtiene el proyecto asociado.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_uuid', 'uuid');
    }

    /**
     * Obtiene el conductor (tercero) asociado.
     */
    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Alias snake_case para la relación con el tercero.
     */
    public function third_party(): BelongsTo
    {
        return $this->thirdParty();
    }
}