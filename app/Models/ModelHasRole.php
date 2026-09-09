<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla pivote model_has_roles.
 *
 * Representa la relación polimórfica entre roles y modelos
 * (usuarios u otros). Usa PK compuesta: role_id + model_id + model_type.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 */
class ModelHasRole extends Model
{
    /**
     * Nombre de la tabla en base de datos.
     */
    protected $table = 'model_has_roles';

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
        'role_id',
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
            'role_id' => 'integer',
            'model_id' => 'integer',
        ];
    }

    /**
     * Relación con el rol.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
