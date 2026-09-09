<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\CostCenter;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de centros de costo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-08
 */
class CostCenterService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name', 'code'];

    protected function getModelInstance(): Model
    {
        return new CostCenter;
    }

    /**
     * Método getAllCostCentersWithPagination.
     */
    public function getAllCostCentersWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllCostCenters.
     */
    public function getAllCostCenters(): Collection
    {
        return $this->all();
    }

    /**
     * Método getCostCenterByUuid.
     */
    public function getCostCenterByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createCostCenter.
     */
    public function createCostCenter(array $data): Model
    {
        return $this->transaction(fn () => CostCenter::create([
            'company_uuid' => $data['company_uuid'],
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'parent_code' => $data['parent_code'] ?? null,
            'level' => $data['level'] ?? 1,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateCostCenter.
     */
    public function updateCostCenter(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var CostCenter $record */
            $record = $this->findByUuid($uuid);
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
                'description' => $data['description'] ?? $record->description,
                'parent_code' => $data['parent_code'] ?? $record->parent_code,
                'level' => $data['level'] ?? $record->level,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteCostCenter.
     */
    public function deleteCostCenter(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('CostCenterService@deleteCostCenter: '.$e->getMessage());
            throw $e;
        }
    }
}
