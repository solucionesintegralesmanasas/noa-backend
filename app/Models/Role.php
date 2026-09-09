<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Modelo Role extendido con soporte multi-tenant.
 *
 * Extiende el modelo base de Spatie Permission y añade la columna
 * `company_uuid` para aislar roles por empresa.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 */
class Role extends SpatieRole
{
    use HasFactory;

    /**
     * Guard por defecto para la API.
     */
    protected string $guard_name = 'api';

    /**
     * Campos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'company_uuid',
    ];
}
