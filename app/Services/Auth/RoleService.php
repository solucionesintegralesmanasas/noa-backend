<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Role;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Servicio para la gestión de roles.
 *
 * Este recurso utiliza 'id' como PK y posee soporte para 'company_uuid'.
 * Se implementa la lógica de filtrado por empresa en la paginación.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class RoleService extends BaseService
{
    /**
     * Campos sobre los cuales se aplica la búsqueda libre.
     *
     * @var array<string>
     */
    protected array $searchableFields = [
        'name',
        'guard_name',
    ];

    /**
     * Retorna la instancia del modelo que este servicio gestiona.
     */
    protected function getModelInstance(): Model
    {
        return new Role;
    }

    /**
     * Método getAllRolesWithPagination.
     */
    public function getAllRolesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllRoles.
     */
    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->get();
    }

    /**
     * Método getRoleById.
     */
    public function getRoleById(int $id, array $relations = []): ?Model
    {
        return $this->buildQuery()->with($relations)->find($id);
    }

    /**
     * Método createRole.
     */
    public function createRole(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'],
                'company_uuid' => $data['company_uuid'] ?? null,
            ]);

            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role;
        });
    }

    /**
     * Método updateRole.
     */
    public function updateRole(int $id, array $data): Model
    {
        return $this->transaction(function () use ($id, $data) {
            $record = $this->getRoleById($id);
            if (! $record) {
                throw new \RuntimeException('Rol no encontrado.');
            }

            $record->update([
                'name' => $data['name'] ?? $record->name,
                'guard_name' => $data['guard_name'] ?? $record->guard_name,
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
            ]);

            if (isset($data['permissions'])) {
                $record->syncPermissions($data['permissions']);
            }

            return $record->fresh();
        });
    }

    /**
     * Método deleteRole.
     */
    public function deleteRole(int $id): void
    {
        if (! $this->buildQuery()->where('id', $id)->delete()) {
            throw new \RuntimeException(
                "No se pudo eliminar el rol con ID: {$id}"
            );
        }
    }
}
