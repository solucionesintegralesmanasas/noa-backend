<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla pivote role_has_permissions.
 *
 * Representa la relación muchos-a-muchos entre roles y permisos.
 * Usa PK compuesta: permission_id + role_id.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 */
class RoleHasPermission extends Model
{
    /**
     * Nombre de la tabla en base de datos.
     */
    protected $table = 'role_has_permissions';

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
        'role_id',
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
            'role_id' => 'integer',
        ];
    }

    /**
     * Relación con el permiso.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    /**
     * Relación con el rol.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
