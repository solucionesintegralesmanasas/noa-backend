<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\ConveyorCapacity;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Capacidad de Transporte Autorizada.
 *
 * Administra la capacidad operativa de las empresas transportadoras, controlando
 * el número de vehículos autorizados por categoría, la capacidad actual y los
 * mínimos de flota propia exigidos por las autoridades de tránsito.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ConveyorCapacityService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['vehicle_type'];

    protected function getModelInstance(): Model
    {
        return new ConveyorCapacity;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'enablingResolution:uuid,resolution_number',
    ];

    /**
     * Método getAllConveyorCapacitiesWithPagination.
     */
    public function getAllConveyorCapacitiesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllConveyorCapacities.
     */
    public function getAllConveyorCapacities(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getConveyorCapacityByUuid.
     */
    public function getConveyorCapacityByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /** Máximo de registros permitidos en inserción múltiple */
    private const MAX_BATCH_SIZE = 500;

    /**
     * Método createConveyorCapacity.
     *
     *
     * @return mixed
     */
    public function createConveyorCapacity(array $data): Model|Collection
    {
        return $this->transaction(function () use ($data) {
            // Si es un arreglo indexado (inserción múltiple)
            if (isset($data[0]) && is_array($data[0])) {
                if (count($data) > self::MAX_BATCH_SIZE) {
                    throw new \RuntimeException('La inserción múltiple excede el límite de '.self::MAX_BATCH_SIZE.' registros.');
                }

                $collection = new Collection;
                foreach ($data as $item) {
                    $collection->push(ConveyorCapacity::create([
                        'enabling_resolution_uuid' => $item['enabling_resolution_uuid'],
                        'vehicle_type' => $item['vehicle_type'],
                        'authorized_capacity' => $item['authorized_capacity'],
                        'current_capacity' => $item['current_capacity'],
                        'minimum_own_capacity' => $item['minimum_own_capacity'],
                        'status' => $item['status'] ?? 1,
                    ]));
                }

                return $collection;
            }

            // Inserción única
            return ConveyorCapacity::create([
                'enabling_resolution_uuid' => $data['enabling_resolution_uuid'],
                'vehicle_type' => $data['vehicle_type'],
                'authorized_capacity' => $data['authorized_capacity'],
                'current_capacity' => $data['current_capacity'],
                'minimum_own_capacity' => $data['minimum_own_capacity'],
                'status' => $data['status'] ?? 1,
            ]);
        });
    }

    /**
     * Método updateConveyorCapacity.
     */
    public function updateConveyorCapacity(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'enabling_resolution_uuid' => $data['enabling_resolution_uuid'] ?? $record->enabling_resolution_uuid,
                'vehicle_type' => $data['vehicle_type'] ?? $record->vehicle_type,
                'authorized_capacity' => $data['authorized_capacity'] ?? $record->authorized_capacity,
                'current_capacity' => $data['current_capacity'] ?? $record->current_capacity,
                'minimum_own_capacity' => $data['minimum_own_capacity'] ?? $record->minimum_own_capacity,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteConveyorCapacity.
     */
    public function deleteConveyorCapacity(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }

    /**
     * Método toggleConveyorCapacityStatus.
     */
    public function toggleConveyorCapacityStatus(string $uuid): Model
    {
        try {
            $this->toggleStatus($uuid, 'status');

            return $this->findByUuid($uuid, relations: self::RELATIONS);
        } catch (\Exception $e) {
            Logger::error('ConveyorCapacityService@toggleConveyorCapacityStatus: '.$e->getMessage());
            throw $e;
        }
    }
}
