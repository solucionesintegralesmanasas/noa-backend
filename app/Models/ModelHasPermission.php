<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla pivote model_has_permissions.
 *
 * Representa la relación polimórfica entre permisos y modelos
 * (usuarios u otros). Usa PK compuesta: permission_id + model_id + model_type.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 */
class ModelHasPermission extends Model
{
    /**
     * Nombre de la tabla en base de datos.
     */
    protected $table = 'model_has_permissions';

    /**
     * Sin timestamps (tabla pivote).
     */
    public $timestamps = false;

    /**
     * Sin clave primaria autoincremental (PK compuesta).
     */
    public $incrementing = false;

    /**
     * Campos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'permission_id',
        'model_type',
        'model_id',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission_id' => 'integer',
            'model_id' => 'integer',
        ];
    }

    /**
     * Relación con el permiso.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
