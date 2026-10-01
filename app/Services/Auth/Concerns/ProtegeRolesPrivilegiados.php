<?php

declare(strict_types=1);

namespace App\Services\Auth\Concerns;

use App\Exceptions\GeneralException;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Salvaguardas para que un ADMIN_EMPRESA pueda gestionar roles y permisos sin escalar privilegios.
 * SUPERADMIN queda exento de todas.
 *
 *  - El rol SUPERADMIN es intocable: no se crea, edita, borra, asigna ni se le cambian permisos.
 *  - Los roles del sistema (SUPERADMIN, ADMIN_EMPRESA, EMPLEADO, AFILIADO, CONDUCTOR) no se eliminan.
 *  - Solo se concede un permiso que el propio actor ya tiene (también al asignar un rol a un usuario:
 *    todos los permisos del rol deben ser suyos). Quitar permisos sí se permite.
 */
trait ProtegeRolesPrivilegiados
{
    private const ROL_PRIVILEGIADO = 'SUPERADMIN';

    private const ROLES_DEL_SISTEMA = ['SUPERADMIN', 'ADMIN_EMPRESA', 'EMPLEADO', 'AFILIADO', 'CONDUCTOR'];

    protected function actorEsSuperadmin(): bool
    {
        $actor = Auth::user();

        return $actor && $actor->hasRole(self::ROL_PRIVILEGIADO);
    }

    private function esRolPrivilegiado(?string $nombre): bool
    {
        return strtoupper((string) $nombre) === self::ROL_PRIVILEGIADO;
    }

    /** @throws GeneralException */
    protected function asegurarNombreDeRolPermitido(?string $nombre): void
    {
        if (! $this->actorEsSuperadmin() && $this->esRolPrivilegiado($nombre)) {
            throw GeneralException::forbidden('El rol SUPERADMIN solo lo gestiona un SUPERADMIN.');
        }
    }

    /** El rol SUPERADMIN no se toca (editar, asignar, quitar, cambiarle permisos). @throws GeneralException */
    protected function asegurarRolNoPrivilegiado(Role|int|null $rol): void
    {
        $rol = is_int($rol) ? Role::query()->find($rol) : $rol;
        if ($rol) {
            $this->asegurarNombreDeRolPermitido($rol->name);
        }
    }

    /** @throws GeneralException */
    protected function asegurarRolEliminable(Role|int|null $rol): void
    {
        $rol = is_int($rol) ? Role::query()->find($rol) : $rol;
        if (! $rol || $this->actorEsSuperadmin()) {
            return;
        }
        if (in_array(strtoupper($rol->name), self::ROLES_DEL_SISTEMA, true)) {
            throw GeneralException::forbidden('Los roles del sistema no se pueden eliminar.');
        }
    }

    /**
     * Cada permiso (nombre o id) debe ser del propio actor.
     *
     * @param  array<int, string|int>  $permisos
     *
     * @throws GeneralException
     */
    protected function asegurarPermisosPropios(array $permisos): void
    {
        $actor = Auth::user();
        if (! $actor || $this->actorEsSuperadmin() || $permisos === []) {
            return;
        }

        $propios = $actor->getAllPermissions()->pluck('name')->all();
        $nombres = collect($permisos)->map(
            fn ($p) => is_numeric($p) ? Permission::query()->find((int) $p)?->name : (string) $p
        )->filter()->all();

        $ajenos = array_diff($nombres, $propios);
        if ($ajenos !== []) {
            throw GeneralException::forbidden('No puedes conceder permisos que no tienes: '.implode(', ', array_slice($ajenos, 0, 5)));
        }
    }

    /** Un rol se puede asignar a un usuario si no es SUPERADMIN y todos sus permisos son del actor. @throws GeneralException */
    protected function asegurarRolAsignable(Role|int|string|null $rol): void
    {
        $modelo = match (true) {
            $rol instanceof Role => $rol,
            is_int($rol) => Role::query()->find($rol),
            is_string($rol) => Role::query()->where('name', $rol)->first(),
            default => null,
        };
        if (! $modelo) {
            return;
        }

        $this->asegurarNombreDeRolPermitido($modelo->name);
        $this->asegurarPermisosPropios($modelo->permissions->pluck('name')->all());
    }
}
