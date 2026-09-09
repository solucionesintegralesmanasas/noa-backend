<?php

declare(strict_types=1);

namespace App\Services\ServiceDeliveryControlSheet;

use App\Models\ServiceInternalControlSubcontracted;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lógica de negocio para la gestión de controles internos de servicio subcontratados.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceInternalControlSubcontractedService extends BaseService
{
    protected array $searchableFields = [
        'vehicle_license_plate',
        'driver_name_and_surname',
        'driver_license_number',
    ];

    protected function getModelInstance(): Model
    {
        return new ServiceInternalControlSubcontracted;
    }

    public function getAllServiceInternalControlSubcontractedsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    public function getAllServiceInternalControlSubcontracteds(): Collection
    {
        return $this->all();
    }

    public function getServiceInternalControlSubcontractedByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    public function createServiceInternalControlSubcontracted(array $data): Model
    {
        return $this->transaction(fn () => ServiceInternalControlSubcontracted::create([
            'uuid' => $data['uuid'] ?? (string) Str::uuid(),
            'vehicle_class_uuid' => $data['vehicle_class_uuid'],
            'service_delivery_control_sheet_uuid' => $data['service_delivery_control_sheet_uuid'],
            'vehicle_license_plate' => $data['vehicle_license_plate'],
            'driver_name_and_surname' => $data['driver_name_and_surname'],
            'driver_license_number' => $data['driver_license_number'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    public function updateServiceInternalControlSubcontracted(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'vehicle_class_uuid' => $data['vehicle_class_uuid'] ?? $record->vehicle_class_uuid,
                'service_delivery_control_sheet_uuid' => $data['service_delivery_control_sheet_uuid'] ?? $record->service_delivery_control_sheet_uuid,
                'vehicle_license_plate' => $data['vehicle_license_plate'] ?? $record->vehicle_license_plate,
                'driver_name_and_surname' => $data['driver_name_and_surname'] ?? $record->driver_name_and_surname,
                'driver_license_number' => $data['driver_license_number'] ?? $record->driver_license_number,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    public function deleteServiceInternalControlSubcontracted(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ServiceInternalControlSubcontractedService@deleteServiceInternalControlSubcontracted: '.$e->getMessage());
            throw $e;
        }
    }
}
