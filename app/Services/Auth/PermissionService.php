<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Permission;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Servicio para la gestión de permisos en el sistema.
 *
 * Administra las capacidades atómicas del sistema. Debido a que el esquema
 * utiliza 'id' como clave primaria y no posee columna 'uuid' ni 'company_uuid',
 * las búsquedas y filtrados se realizan sobre el identificador entero.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class PermissionService extends BaseService
{
    /**
     * Campos sobre los cuales se aplica la búsqueda libre.
     *
     * @var array<string>
     */
    protected array $searchableFields = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * Retorna la instancia del modelo que este servicio gestiona.
     */
    protected function getModelInstance(): Model
    {
        return new Permission;
    }

    /**
     * Método getAllPermissionsWithPagination.
     */
    public function getAllPermissionsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = ''
    ): LengthAwarePaginator {
        // No pasamos companyUuid ya que la tabla no tiene dicha columna
        return $this->getPaginatedData($perPage, $page, $search);
    }

    /**
     * Método getAllPermissions.
     */
    public function getAllPermissions(): Collection
    {
        return $this->all();
    }

    /**
     * Método getPermissionById.
     */
    public function getPermissionById(int $id, array $relations = []): ?Model
    {
        // Usamos el ID ya que no hay columna UUID
        return $this->buildQuery()->with($relations)->find($id);
    }

    /**
     * Método createPermission.
     */
    public function createPermission(array $data): Model
    {
        return $this->transaction(fn () => Permission::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'],
            'description' => $data['description'] ?? null,
        ]));
    }

    /**
     * Método updatePermission.
     */
    public function updatePermission(int $id, array $data): Model
    {
        return $this->transaction(function () use ($id, $data) {
            $record = $this->getPermissionById($id);
            if (! $record) {
                throw new \RuntimeException('Permiso no encontrado.');
            }

            $record->update([
                'name' => $data['name'] ?? $record->name,
                'guard_name' => $data['guard_name'] ?? $record->guard_name,
                'description' => $data['description'] ?? $record->description,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deletePermission.
     */
    public function deletePermission(int $id): void
    {
        // El método delete de BaseService suele esperar UUID si está configurado así,
        // pero si el modelo no tiene HasUuids, usará el ID.
        if (! $this->buildQuery()->where('id', $id)->delete()) {
            throw new \RuntimeException(
                "No se pudo eliminar el registro de Permiso con ID: {$id}"
            );
        }
    }
}
