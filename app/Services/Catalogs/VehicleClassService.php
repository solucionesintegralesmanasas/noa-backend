<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\VehicleClass;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de ClaseVehiculo.
 *
 * Este servicio administra las categorías técnicas de los vehículos, permitiendo
 * estandarizar la clasificación de la flota para fines operativos y regulatorios.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class VehicleClassService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['class_code_class', 'description'];

    protected function getModelInstance(): Model
    {
        return new VehicleClass;
    }

    /**
     * Método getAllVehicleClassesWithPagination.
     */
    public function getAllVehicleClassesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'class_code_class', 'description']);
    }

    /**
     * Método getAllVehicleClasses.
     */
    public function getAllVehicleClasses(): Collection
    {
        return $this->all(columns: ['uuid', 'class_code_class', 'description']);
    }

    /**
     * Método getVehicleClassByUuid.
     */
    public function getVehicleClassByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createVehicleClass.
     */
    public function createVehicleClass(array $data): Model
    {
        return $this->transaction(fn () => VehicleClass::create([
            'class_code_class' => $data['class_code_class'],
            'description' => $data['description'],
        ]));
    }

    /**
     * Método updateVehicleClass.
     */
    public function updateVehicleClass(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var VehicleClass $record */
            $record = VehicleClass::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'class_code_class' => $data['class_code_class'] ?? $record->class_code_class,
                'description' => $data['description'] ?? $record->description,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteVehicleClass.
     */
    public function deleteVehicleClass(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('VehicleClassService@deleteVehicleClass: '.$e->getMessage());
            throw $e;
        }
    }
}
