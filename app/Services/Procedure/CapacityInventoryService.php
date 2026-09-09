<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\CapacityInventory;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de InventarioCapacidad.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de InventarioCapacidad en el dominio del negocio.
 * Se encarga de controlar el inventario de capacidades transportadoras y tarjetas
 * de operación asociadas a los trámites de las empresas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class CapacityInventoryService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [];

    protected function getModelInstance(): Model
    {
        return new CapacityInventory;
    }

    /**
     * Método getAllCapacityInventoriesWithPagination.
     */
    public function getAllCapacityInventoriesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllCapacityInventories.
     */
    public function getAllCapacityInventories(): Collection
    {
        return $this->all();
    }

    /**
     * Método getCapacityInventoryByUuid.
     */
    public function getCapacityInventoryByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createCapacityInventory.
     */
    public function createCapacityInventory(array $data): Model
    {
        return $this->transaction(fn () => CapacityInventory::create([
            'company_uuid' => $data['company_uuid'],
            'conveyor_capacity_uuid' => $data['conveyor_capacity_uuid'],
            'procedure_uuid' => $data['procedure_uuid'],
            'used_conveyor_capacity' => $data['used_conveyor_capacity'],
            'used_operating_cards' => $data['used_operating_cards'],
        ]));
    }

    /**
     * Método updateCapacityInventory.
     */
    public function updateCapacityInventory(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'conveyor_capacity_uuid' => $data['conveyor_capacity_uuid'] ?? $record->conveyor_capacity_uuid,
                'procedure_uuid' => $data['procedure_uuid'] ?? $record->procedure_uuid,
                'used_conveyor_capacity' => $data['used_conveyor_capacity'] ?? $record->used_conveyor_capacity,
                'used_operating_cards' => $data['used_operating_cards'] ?? $record->used_operating_cards,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteCapacityInventory.
     */
    public function deleteCapacityInventory(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('CapacityInventoryService@deleteCapacityInventory: '.$e->getMessage());
            throw $e;
        }
    }
}
