<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RoleHasPermission;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Servicio para gestionar la relación entre roles y permisos.
 *
 * Este recurso carece de columna UUID y utiliza una PK compuesta (permission_id, role_id).
 * Las operaciones se adaptan para manejar la búsqueda por esta clave compuesta.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class RoleHasPermissionService extends BaseService
{
    protected array $searchableFields = [];

    protected function getModelInstance(): Model
    {
        return new RoleHasPermission;
    }

    /**
     * Método getAllRoleHasPermissionsWithPagination.
     */
    public function getAllRoleHasPermissionsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = ''
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search);
    }

    /**
     * Método getAllRoleHasPermissions.
     */
    public function getAllRoleHasPermissions(): Collection
    {
        return $this->all();
    }

    /**
     * Método getByCompositeKey.
     */
    public function getByCompositeKey(int $permissionId, int $roleId): ?Model
    {
        return $this->buildQuery()
            ->where('permission_id', $permissionId)
            ->where('role_id', $roleId)
            ->first();
    }

    /**
     * Método createRoleHasPermission.
     */
    public function createRoleHasPermission(array $data): Model
    {
        return $this->transaction(fn () => RoleHasPermission::create([
            'permission_id' => $data['permission_id'],
            'role_id' => $data['role_id'],
        ]));
    }

    /**
     * Método deleteByCompositeKey.
     */
    public function deleteByCompositeKey(int $permissionId, int $roleId): void
    {
        $this->transaction(function () use ($permissionId, $roleId) {
            $record = $this->getByCompositeKey($permissionId, $roleId);
            if ($record) {
                $record->delete();
            }
        });
    }
}
