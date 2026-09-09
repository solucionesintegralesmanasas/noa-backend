<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\Maintenance;
use App\Services\BaseService;
use App\Services\Pdf\PdfService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio altamente detallado para la gestión integral de RegistroMantenimiento.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class MaintenanceService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [
        'maintenance_type',
        'service_description',
        'mechanic_name',
        'workshop_name',
        'invoice_number',
        'notes',
    ];

    public function __construct(
        private readonly MaintenancePartService $maintenancePartService,
        private readonly VehicleService $vehicleService
    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new Maintenance;
    }

    /**
     * Método query.
     */
    public function query(): Builder
    {
        return parent::query()->with(['maintenanceParts', 'vehicle']);
    }

    /**
     * Método getAllMaintenancesWithPagination.
     */
    public function getAllMaintenancesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with('maintenanceParts');

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }

                $q->orWhereHas('vehicle', function ($qVeh) use ($search) {
                    $qVeh->where('vehicle_license_plate', 'like', "%{$search}%")
                        ->orWhere('internal_number', 'like', "%{$search}%");
                });
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($m) => [
            'uuid' => $m->uuid,
            'maintenance_date' => $m->maintenance_date,
            'workshop_name' => $m->workshop_name,
            'service_description' => $m->service_description,
            'labor_cost' => (float) $m->labor_cost,
            'parts_cost' => (float) $m->parts_cost,
            'status' => $m->status,
            'vehicle' => $m->vehicle ? [
                'vehicle_license_plate' => $m->vehicle->vehicle_license_plate,
                'internal_number' => $m->vehicle->internal_number,
            ] : null,
        ]);

        return $paginator;
    }

    /**
     * Método getAllMaintenances.
     */
    public function getAllMaintenances(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with('maintenanceParts');

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        return $query->get();
    }

    /**
     * Método getMaintenanceByUuid.
     */
    public function getMaintenanceByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createMaintenance.
     */
    public function createMaintenance(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $maintenance = Maintenance::create([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'],
                'maintenance_type' => $data['maintenance_type'],
                'mileage' => $data['mileage'],
                'service_description' => $data['service_description'],
                'mechanic_name' => $data['mechanic_name'] ?? null,
                'workshop_name' => $data['workshop_name'] ?? null,
                'maintenance_date' => $data['maintenance_date'],
                'labor_cost' => $data['labor_cost'] ?? 0,
                'parts_cost' => $data['parts_cost'] ?? 0,
                'invoice_number' => $data['invoice_number'] ?? null,
                'next_maintenance_date' => $data['next_maintenance_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'Pendiente',
            ]);

            if (isset($data['parts']) && is_array($data['parts'])) {
                foreach ($data['parts'] as $part) {
                    $this->maintenancePartService->createMaintenancePart(array_merge($part, [
                        'maintenance_uuid' => $maintenance->uuid,
                    ]));
                }
            }

            // Actualizar información técnica del vehículo usando el servicio
            DB::table('vehicles')
                ->where('uuid', $data['vehicle_uuid'])
                ->update([
                    'address_type' => $data['address_type'] ?? $data['steering_type'] ?? '',
                    'steering_type' => $data['steering_type'] ?? null,
                    'transmission_type' => $data['transmission_type'] ?? null,
                    'number_of_speeds' => $data['number_of_speeds'] ?? null,
                    'bearing_type' => $data['bearing_type'] ?? null,
                    'rear_suspension' => $data['rear_suspension'] ?? null,
                    'number_of_tires' => $data['number_of_tires'] ?? null,
                    'rim_size' => $data['rim_size'] ?? null,
                    'rim_material' => $data['rim_material'] ?? null,
                    'front_brake_type' => $data['front_brake_type'] ?? null,
                    'rear_brake_type' => $data['rear_brake_type'] ?? null,
                    'number_of_windows' => $data['number_of_windows'] ?? null,
                ]);

            return $maintenance->load('maintenanceParts');
        });
    }

    /**
     * Método updateMaintenance.
     */
    public function updateMaintenance(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'maintenance_type' => $data['maintenance_type'] ?? $record->maintenance_type,
                'mileage' => $data['mileage'] ?? $record->mileage,
                'service_description' => $data['service_description'] ?? $record->service_description,
                'mechanic_name' => $data['mechanic_name'] ?? $record->mechanic_name,
                'workshop_name' => $data['workshop_name'] ?? $record->workshop_name,
                'maintenance_date' => $data['maintenance_date'] ?? $record->maintenance_date,
                'labor_cost' => $data['labor_cost'] ?? $record->labor_cost,
                'parts_cost' => $data['parts_cost'] ?? $record->parts_cost,
                'invoice_number' => $data['invoice_number'] ?? $record->invoice_number,
                'next_maintenance_date' => $data['next_maintenance_date'] ?? $record->next_maintenance_date,
                'notes' => $data['notes'] ?? $record->notes,
                'status' => $data['status'] ?? $record->status,
            ]);

            if (isset($data['parts']) && is_array($data['parts'])) {
                // Eliminar repuestos anteriores de forma limpia
                $record->maintenanceParts()->delete();

                // Registrar los nuevos repuestos
                foreach ($data['parts'] as $part) {
                    $this->maintenancePartService->createMaintenancePart(array_merge($part, [
                        'maintenance_uuid' => $record->uuid,
                    ]));
                }
            }

            // Actualizar información técnica del vehículo
            DB::table('vehicles')
                ->where('uuid', $data['vehicle_uuid'])
                ->update([
                    'address_type' => $data['address_type'] ?? $data['steering_type'] ?? '',
                    'steering_type' => $data['steering_type'] ?? null,
                    'transmission_type' => $data['transmission_type'] ?? null,
                    'number_of_speeds' => $data['number_of_speeds'] ?? null,
                    'bearing_type' => $data['bearing_type'] ?? null,
                    'rear_suspension' => $data['rear_suspension'] ?? null,
                    'number_of_tires' => $data['number_of_tires'] ?? null,
                    'rim_size' => $data['rim_size'] ?? null,
                    'rim_material' => $data['rim_material'] ?? null,
                    'front_brake_type' => $data['front_brake_type'] ?? null,
                    'rear_brake_type' => $data['rear_brake_type'] ?? null,
                    'number_of_windows' => $data['number_of_windows'] ?? null,
                ]);

            return $record->fresh()->load('maintenanceParts');
        });
    }

    /**
     * Método deleteMaintenance.
     */
    public function deleteMaintenance(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('MaintenanceService@deleteMaintenance: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método generateMaintenanceHistoryPdf.
     */
    public function generateMaintenanceHistoryPdf(string $vehicleUuid): array
    {
        $pdfService = app(PdfService::class);

        return $pdfService->generateMaintenanceHistoryPdf($vehicleUuid);
    }
}
