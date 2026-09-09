<?php

declare(strict_types=1);

namespace App\Services\ServiceDeliveryControlSheet;

use App\Models\ServiceInternalControl;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lógica de negocio para la gestión de controles internos de servicio.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceInternalControlService extends BaseService
{
    protected array $searchableFields = [];

    protected function getModelInstance(): Model
    {
        return new ServiceInternalControl;
    }

    public function getAllServiceInternalControlsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    public function getAllServiceInternalControls(): Collection
    {
        return $this->all();
    }

    public function getServiceInternalControlByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    public function createServiceInternalControl(array $data): Model
    {
        return $this->transaction(fn () => ServiceInternalControl::create([
            'uuid' => $data['uuid'] ?? (string) Str::uuid(),
            'company_uuid' => $data['company_uuid'],
            'vehicle_uuid' => $data['vehicle_uuid'],
            'third_party_uuid' => $data['third_party_uuid'],
            'fuec_uuid' => $data['fuec_uuid'] ?? null,
            'service_delivery_control_sheet_uuid' => $data['service_delivery_control_sheet_uuid'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    public function updateServiceInternalControl(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'fuec_uuid' => $data['fuec_uuid'] ?? $record->fuec_uuid,
                'service_delivery_control_sheet_uuid' => $data['service_delivery_control_sheet_uuid'] ?? $record->service_delivery_control_sheet_uuid,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    public function deleteServiceInternalControl(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ServiceInternalControlService@deleteServiceInternalControl: '.$e->getMessage());
            throw $e;
        }
    }
}
