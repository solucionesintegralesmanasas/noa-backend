<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Branch que representa la tabla branches.
 * Catálogo de sucursales o sedes operativas
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class Branch extends Model
{
    use BelongsToCompany, FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'branches';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'name',
        'address',
        'municipality_uuid',
        'is_primary',
        'status',
    ];

    /**
     * Atributos que deben ser ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
    ];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'status' => 'boolean',
    ];

    /**
     * Relación con City.
     *
     * @return BelongsTo
     */
    public function municipality()
    {
        return $this->belongsTo(City::class, 'municipality_uuid', 'uuid');
    }
}
