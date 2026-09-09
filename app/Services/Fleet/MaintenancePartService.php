<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\MaintenancePart;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de RepuestoMantenimiento.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class MaintenancePartService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['part_name', 'part_code', 'notes'];

    protected function getModelInstance(): Model
    {
        return new MaintenancePart;
    }

    /**
     * Método getAllMaintenancePartsWithPagination.
     */
    public function getAllMaintenancePartsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($companyUuid) {
            $query->whereHas('maintenance', function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            });
        }

        if ($thirdPartyUuid) {
            $query->whereHas('maintenance.vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Método getAllMaintenanceParts.
     */
    public function getAllMaintenanceParts(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

        if ($companyUuid) {
            $query->whereHas('maintenance', function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            });
        }

        if ($thirdPartyUuid) {
            $query->whereHas('maintenance.vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        return $query->get();
    }

    /**
     * Método getMaintenancePartByUuid.
     */
    public function getMaintenancePartByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createMaintenancePart.
     */
    public function createMaintenancePart(array $data): Model
    {
        return $this->transaction(fn () => MaintenancePart::create([
            'maintenance_uuid' => $data['maintenance_uuid'],
            'part_name' => $data['part_name'],
            'part_code' => $data['part_code'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit_cost' => $data['unit_cost'] ?? 0,
            'supplier_uuid' => $data['supplier_uuid'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * Método updateMaintenancePart.
     */
    public function updateMaintenancePart(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'part_name' => $data['part_name'] ?? $record->part_name,
                'part_code' => $data['part_code'] ?? $record->part_code,
                'quantity' => $data['quantity'] ?? $record->quantity,
                'unit_cost' => $data['unit_cost'] ?? $record->unit_cost,
                'supplier_uuid' => $data['supplier_uuid'] ?? $record->supplier_uuid,
                'notes' => $data['notes'] ?? $record->notes,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteMaintenancePart.
     */
    public function deleteMaintenancePart(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('MaintenancePartService@deleteMaintenancePart: '.$e->getMessage());
            throw $e;
        }
    }
}
