<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ModelHasRole;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Servicio para gestionar la asignación de roles a modelos.
 *
 * Este recurso carece de columna UUID y utiliza una PK compuesta (role_id, model_id, model_type).
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class ModelHasRoleService extends BaseService
{
    protected array $searchableFields = ['model_type'];

    protected function getModelInstance(): Model
    {
        return new ModelHasRole;
    }

    /**
     * Método getAllModelHasRolesWithPagination.
     */
    public function getAllModelHasRolesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = ''
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search);
    }

    /**
     * Método getByCompositeKey.
     */
    public function getByCompositeKey(int $roleId, int $modelId, string $modelType): ?Model
    {
        return $this->buildQuery()
            ->where('role_id', $roleId)
            ->where('model_id', $modelId)
            ->where('model_type', $modelType)
            ->first();
    }

    /**
     * Método createModelHasRole.
     */
    public function createModelHasRole(array $data): Model
    {
        return $this->transaction(fn () => ModelHasRole::create([
            'role_id' => $data['role_id'],
            'model_id' => $data['model_id'],
            'model_type' => $data['model_type'],
        ]));
    }

    /**
     * Método deleteByCompositeKey.
     */
    public function deleteByCompositeKey(int $roleId, int $modelId, string $modelType): void
    {
        $this->transaction(function () use ($roleId, $modelId, $modelType) {
            $record = $this->getByCompositeKey($roleId, $modelId, $modelType);
            if ($record) {
                $record->delete();
            }
        });
    }
}
