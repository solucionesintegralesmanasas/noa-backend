<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\VehicleBranch;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio para gestionar las sucursales asignadas a los vehículos.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-11
 */
class VehicleBranchService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [];

    protected function getModelInstance(): Model
    {
        return new VehicleBranch;
    }

    /**
     * Método getAllVehicleBranchesWithPagination.
     */
    public function getAllVehicleBranchesWithPagination(int $perPage = 15, int $page = 1, ?string $companyUuid = null, ?string $vehicleUuid = null): LengthAwarePaginator
    {
        $columns = [
            'uuid',
            'company_uuid',
            'vehicle_uuid',
            'branch_uuid',
            'entry_date',
            'exit_date',
            'exit_type',
            'is_active',
        ];

        $query = $this->query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        return $query->paginate($perPage, $columns, 'page', $page);
    }

    /**
     * Método getAllVehicleBranches.
     */
    public function getAllVehicleBranches(?string $companyUuid = null, ?string $vehicleUuid = null): Collection
    {
        $query = $this->query();

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        return $query->get();
    }

    /**
     * Método getVehicleBranchByUuid.
     */
    public function getVehicleBranchByUuid(string $uuid): ?Model
    {
        $vehicleBranch = $this->findByUuid($uuid);
        if ($vehicleBranch) {
            $vehicleBranch->load(['vehicle', 'branch']);
        }

        return $vehicleBranch;
    }

    /**
     * Método createVehicleBranch.
     */
    public function createVehicleBranch(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $entryDate = $data['entry_date'] ?? now()->toDateString();

            return VehicleBranch::create([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'],
                'branch_uuid' => $data['branch_uuid'],
                'entry_date' => $entryDate,
                'exit_date' => $data['exit_date'] ?? $entryDate,
                'exit_type' => $data['exit_type'] ?? 'ENTRADA',
                'is_active' => $data['is_active'] ?? 1,
            ]);
        });
    }

    /**
     * Método updateVehicleBranch.
     */
    public function updateVehicleBranch(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'branch_uuid' => $data['branch_uuid'] ?? $record->branch_uuid,
                'entry_date' => $data['entry_date'] ?? $record->entry_date,
                'exit_date' => $data['exit_date'] ?? $record->exit_date,
                'exit_type' => $data['exit_type'] ?? $record->exit_type,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteVehicleBranch.
     */
    public function deleteVehicleBranch(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException('No se pudo eliminar el registro de sucursal del vehículo.');
        }
    }
}
