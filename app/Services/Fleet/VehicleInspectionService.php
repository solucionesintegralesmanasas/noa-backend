<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\VehicleInspection;
use App\Services\BaseService;
use App\Services\Notifications\NotificationsService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio altamente detallado para la gestión integral de InspeccionVehiculo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class VehicleInspectionService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['inspector_name', 'notes'];

    public function __construct(
        private readonly InspectionResultService $resultService
    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new VehicleInspection;
    }

    /**
     * Método query.
     */
    public function query(): Builder
    {
        return parent::query()->with([
            'inspectionResults',
            'vehicle.brand',
            'driver.driverLicenses',
        ]);
    }

    /**
     * Método getAllVehicleInspectionsWithPagination.
     */
    public function getAllVehicleInspectionsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null,
        ?string $vehicleUuid = null
    ): LengthAwarePaginator {
        // Optimizamos la consulta para la tabla de Vue:
        // No cargamos los 'inspectionResults' y seleccionamos solo las columnas requeridas
        $query = parent::query()
            ->select(['id', 'uuid', 'company_uuid', 'vehicle_uuid', 'driver_uuid', 'inspection_date', 'inspector_name', 'mileage'])
            ->with([
                'vehicle:uuid,vehicle_license_plate,model,brand_uuid,third_party_uuid',
                'vehicle.brand:uuid,description',
                'driver:uuid,first_name,last_name,document_number',
            ]);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('inspector_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function ($qv) use ($search) {
                        $qv->where('vehicle_license_plate', 'like', "%{$search}%");
                    })
                    ->orWhereHas('driver', function ($qd) use ($search) {
                        $qd->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderBy('inspection_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Precalcula el kilometraje total de cada vehículo en una sola consulta para evitar N+1 queries
        $vehicleUuids = $paginator->getCollection()->pluck('vehicle_uuid')->filter()->unique()->toArray();

        $totalMileages = DB::table('vehicle_inspections')
            ->whereIn('vehicle_uuid', $vehicleUuids)
            ->when($companyUuid, function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            })
            ->select('vehicle_uuid', DB::raw('SUM(mileage) as total_mileage'))
            ->groupBy('vehicle_uuid')
            ->pluck('total_mileage', 'vehicle_uuid');

        $paginator->getCollection()->transform(function ($inspection) use ($totalMileages) {
            $totalMileage = (float) ($totalMileages[$inspection->vehicle_uuid] ?? 0);

            return [
                'id' => $inspection->id,
                'uuid' => $inspection->uuid,
                'inspection_date' => $inspection->inspection_date,
                'inspector_name' => $inspection->inspector_name,
                'mileage' => (float) $inspection->mileage,
                'vehicle' => $inspection->vehicle ? [
                    'uuid' => $inspection->vehicle->uuid,
                    'vehicle_license_plate' => $inspection->vehicle->vehicle_license_plate,
                    'model' => $inspection->vehicle->model,
                    'brand' => $inspection->vehicle->brand ? [
                        'uuid' => $inspection->vehicle->brand->uuid,
                        'description' => $inspection->vehicle->brand->description,
                    ] : null,
                ] : null,
                'driver' => $inspection->driver ? [
                    'uuid' => $inspection->driver->uuid,
                    'first_name' => $inspection->driver->first_name,
                    'last_name' => $inspection->driver->last_name,
                    'document_number' => $inspection->driver->document_number,
                ] : null,
            ];
        });

        return $paginator;
    }

    /**
     * Método getAllVehicleInspections.
     */
    public function getAllVehicleInspections(?string $companyUuid = null, ?string $thirdPartyUuid = null, ?string $vehicleUuid = null): Collection
    {
        $query = $this->query()->with('inspectionResults');

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        return $query->get();
    }

    /**
     * Método getVehicleInspectionByUuid.
     */
    public function getVehicleInspectionByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createVehicleInspection.
     */
    public function createVehicleInspection(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $inspection = VehicleInspection::create([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'],
                'inspection_date' => $data['inspection_date'],
                'inspector_name' => $data['inspector_name'] ?? null,
                'mileage' => $data['mileage'] ?? null,
                'driver_uuid' => $data['driver_uuid'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (isset($data['results']) && is_array($data['results'])) {
                foreach ($data['results'] as $result) {
                    $this->resultService->createInspectionResult(array_merge($result, [
                        'inspection_uuid' => $inspection->uuid,
                    ]));
                }
            }

            // Sincronizar notificaciones de inmediato para actualizar la inspección pendiente
            try {
                app(NotificationsService::class)->syncNotifications($inspection->company_uuid);
            } catch (\Exception $e) {
                Logger::warning('Error syncing notifications after vehicle inspection creation: '.$e->getMessage());
            }

            return $inspection->load('inspectionResults');
        });
    }

    /**
     * Método updateVehicleInspection.
     */
    public function updateVehicleInspection(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'inspection_date' => $data['inspection_date'] ?? $record->inspection_date,
                'inspector_name' => $data['inspector_name'] ?? $record->inspector_name,
                'mileage' => $data['mileage'] ?? $record->mileage,
                'driver_uuid' => $data['driver_uuid'] ?? $record->driver_uuid,
                'notes' => $data['notes'] ?? $record->notes,
            ]);

            if (isset($data['results']) && is_array($data['results'])) {
                // Eliminar resultados anteriores de forma limpia
                $record->inspectionResults()->delete();

                // Registrar los nuevos resultados de inspección
                foreach ($data['results'] as $result) {
                    $this->resultService->createInspectionResult(array_merge($result, [
                        'inspection_uuid' => $record->uuid,
                    ]));
                }
            }

            // Sincronizar notificaciones de inmediato para actualizar la inspección pendiente
            try {
                app(NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::warning('Error syncing notifications after vehicle inspection update: '.$e->getMessage());
            }

            return $record->fresh()->load('inspectionResults');
        });
    }

    /**
     * Método deleteVehicleInspection.
     */
    public function deleteVehicleInspection(string $uuid): void
    {
        try {
            $record = $this->findByUuid($uuid);
            $companyUuid = $record ? $record->company_uuid : null;

            $this->delete($uuid);

            if ($companyUuid) {
                try {
                    app(NotificationsService::class)->syncNotifications($companyUuid);
                } catch (\Exception $e) {
                    Logger::warning('Error syncing notifications after vehicle inspection deletion: '.$e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Logger::error('VehicleInspectionService@deleteVehicleInspection: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método getTotalMileage.
     */
    public function getTotalMileage(?string $companyUuid = null, ?string $thirdPartyUuid = null, ?string $vehicleUuid = null): float
    {
        $query = $this->query();

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        return (float) $query->sum('mileage');
    }
}
