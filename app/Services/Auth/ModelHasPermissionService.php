<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ModelHasPermission;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Servicio para gestionar la asignación de permisos a modelos.
 *
 * Este recurso carece de columna UUID y utiliza una PK compuesta (permission_id, model_id, model_type).
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class ModelHasPermissionService extends BaseService
{
    protected array $searchableFields = ['model_type'];

    protected function getModelInstance(): Model
    {
        return new ModelHasPermission;
    }

    /**
     * Método getAllModelHasPermissionsWithPagination.
     */
    public function getAllModelHasPermissionsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = ''
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search);
    }

    /**
     * Método getByCompositeKey.
     */
    public function getByCompositeKey(int $permissionId, int $modelId, string $modelType): ?Model
    {
        return $this->buildQuery()
            ->where('permission_id', $permissionId)
            ->where('model_id', $modelId)
            ->where('model_type', $modelType)
            ->first();
    }

    /**
     * Método createModelHasPermission.
     */
    public function createModelHasPermission(array $data): Model
    {
        return $this->transaction(fn () => ModelHasPermission::create([
            'permission_id' => $data['permission_id'],
            'model_id' => $data['model_id'],
            'model_type' => $data['model_type'],
        ]));
    }

    /**
     * Método deleteByCompositeKey.
     */
    public function deleteByCompositeKey(int $permissionId, int $modelId, string $modelType): void
    {
        $this->transaction(function () use ($permissionId, $modelId, $modelType) {
            $record = $this->getByCompositeKey($permissionId, $modelId, $modelType);
            if ($record) {
                $record->delete();
            }
        });
    }
}
