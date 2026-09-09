<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Modelo Permission extendido con campo de descripción.
 *
 * Extiende el modelo base de Spatie Permission y añade el campo
 * `description` para documentar la capacidad atómica del permiso.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 */
class Permission extends SpatiePermission
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
        'module',
        'description',
    ];
}
