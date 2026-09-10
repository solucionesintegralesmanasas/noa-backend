<?php

declare(strict_types=1);

namespace App\Services\ServiceDeliveryControlSheet;

use App\Models\Project;
use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Models\Signature;
use App\Services\BaseService;
use App\Services\Signature\SignatureService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para la gestión de hojas de control de entrega de servicios.
 * Soporta: proyecto base, N recorridos por planilla, multi-día padre/hijos,
 * vehículo interno (directo) y vehículo externo/subcontratado de plataforma.
 *
 * @author Darwin Montes
 * @version V 1.1.0
 * @since V 1.1.0
 * @created 2026-06-19
 */
class ServiceDeliveryControlSheetService extends BaseService
{
    public function __construct(
        private readonly ServiceInternalControlService $serviceInternalControlService,
        private readonly ServiceInternalControlSubcontractedService $serviceInternalControlSubcontractedService,
        private readonly SignatureService $signatureService
    ) {
        parent::__construct();
    }

    protected array $searchableFields = [
        'official_name_and_surname',
        'daily_route',
    ];

    protected function getModelInstance(): Model
    {
        return new ServiceDeliveryControlSheet;
    }

    public function getAllServiceDeliveryControlSheetsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $projectUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()
            ->whereNull('parent_uuid')
            ->with(['project', 'routes', 'children'])
            ->withCount(['children as children_total'])
            ->withCount(['children as children_open' => function ($q) {
                $q->where('is_active', true);
            }]);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($projectUuid) {
            $query->where('project_uuid', $projectUuid);
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
                // Buscar también por nombre de proyecto
                $q->orWhereHas('project', fn ($pq) => $pq->where('project_name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($item) {
            $item->setAttribute('control_status', $this->resolveControlStatus($item));
            $item->setAttribute('routes_total', $item->routes ? $item->routes->count() : 0);

            return $item;
        });

        return $paginator;
    }

    public function getAllServiceDeliveryControlSheets(): Collection
    {
        return $this->query()
            ->whereNull('parent_uuid')
            ->with(['project', 'routes', 'children.routes', 'children.internalControl', 'children.subcontractedControl'])
            ->get();
    }

    private function resolveControlStatus(Model $record): string
    {
        $total = (int) ($record->children_total ?? 0);
        $open = (int) ($record->children_open ?? 0);

        if ($total > 0) {
            if ($open === 0) {
                return 'CERRADA';
            }
            if ($open < $total) {
                return 'PARCIAL';
            }

            return 'ABIERTA';
        }

        return $record->is_active ? 'ABIERTA' : 'CERRADA';
    }

    public function getServiceDeliveryControlSheetByUuid(string $uuid): ?Model
    {
        return ServiceDeliveryControlSheet::where('uuid', $uuid)
            ->with(['project', 'routes', 'children.routes', 'children.internalControl', 'children.subcontractedControl'])
            ->first();
    }

    /**
     * Normaliza el tipo de planilla a los 4 valores permitidos.
     */
    private function normalizeType(mixed $type): string
    {
        $allowed = ['DIRECTO_CON_LA_EMPRESA', 'SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'];

        return in_array($type, $allowed, true) ? $type : 'DIRECTO_CON_LA_EMPRESA';
    }

    private function isExternalType(string $type): bool
    {
        return in_array($type, ['SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA'], true);
    }

    /**
     * Valida que las fechas de la planilla estén contenidas en el proyecto.
     */
    private function validateProjectDates(?string $projectUuid, ?string $startDate, ?string $endDate): void
    {
        if (! $projectUuid) {
            return;
        }
        $project = Project::where('uuid', $projectUuid)->first();
        if (! $project) {
            throw ValidationException::withMessages(['project_uuid' => 'El proyecto seleccionado no existe.']);
        }
        if ($startDate && $project->start_date && Carbon::parse($startDate)->lt(Carbon::parse($project->start_date))) {
            throw ValidationException::withMessages(['start_date' => 'La fecha de inicio no puede ser anterior al inicio del proyecto ('.$project->start_date->format('d/m/Y').').']);
        }
        if ($endDate && $project->completion_date && Carbon::parse($endDate)->gt(Carbon::parse($project->completion_date))) {
            throw ValidationException::withMessages(['end_date' => 'La fecha de fin no puede superar el fin del proyecto ('.$project->completion_date->format('d/m/Y').').']);
        }
    }

    /**
     * Construye daily_route legacy a partir de routes (compatibilidad PDF/listado).
     */
    private function buildDailyRoute(?string $dailyRoute, mixed $routes): ?string
    {
        if (is_array($routes) && count($routes) > 0) {
            $names = [];
            foreach ($routes as $r) {
                $label = trim(($r['origin'] ?? '').' - '.($r['destination'] ?? ''), ' -');
                if ($label) {
                    $names[] = $label;
                }
            }
            if (! empty($names)) {
                return mb_substr(implode(' · ', $names), 0, 255);
            }
        }

        return $dailyRoute;
    }

    private function syncRoutes(ServiceDeliveryControlSheet $record, mixed $routes): void
    {
        if (! is_array($routes)) {
            return;
        }
        $record->routes()->delete();
        foreach (array_values($routes) as $index => $routeData) {
            if (! is_array($routeData)) {
                continue;
            }
            if (empty($routeData['origin']) && empty($routeData['destination'])) {
                continue;
            }
            ServiceDeliveryControlSheetRoute::create([
                'uuid' => (string) Str::uuid(),
                'service_delivery_control_sheet_uuid' => $record->uuid,
                'order_index' => $index + 1,
                'origin' => $routeData['origin'] ?? null,
                'destination' => $routeData['destination'] ?? null,
                'is_active' => true,
            ]);
        }
    }

    private function createControlForSheet(ServiceDeliveryControlSheet $sheet, array $data, string $type, bool $isChild = false): void
    {
        if (! $this->isExternalType($type)) {
            $this->serviceInternalControlService->createServiceInternalControl([
                'company_uuid' => $sheet->company_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'is_active' => $isChild ? false : ($data['is_active'] ?? true),
            ]);

            return;
        }

        // Vehículo externo / subcontratado: placa + conductor en texto libre.
        // Si además viene vehicle_uuid de plataforma, se guarda también el vínculo interno para trazabilidad.
        if (! empty($data['vehicle_uuid']) || ! empty($data['third_party_uuid'])) {
            $this->serviceInternalControlService->createServiceInternalControl([
                'company_uuid' => $sheet->company_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'is_active' => $isChild ? false : ($data['is_active'] ?? true),
            ]);
        }

        if (! empty($data['vehicle_license_plate']) && ! empty($data['driver_name_and_surname'])) {
            $this->serviceInternalControlSubcontractedService->createServiceInternalControlSubcontracted([
                'vehicle_class_uuid' => $data['vehicle_class_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $sheet->uuid,
                'vehicle_license_plate' => $data['vehicle_license_plate'],
                'driver_name_and_surname' => $data['driver_name_and_surname'],
                'driver_license_number' => $data['driver_license_number'] ?? 'N/A',
                'is_active' => $isChild ? false : ($data['is_active'] ?? true),
            ]);
        }
    }

    public function createServiceDeliveryControlSheet(array $data): Model
    {
        $type = $this->normalizeType($data['type_of_control_sheet'] ?? 'DIRECTO_CON_LA_EMPRESA');

        return $this->transaction(function () use ($data, $type) {
            $startRaw = $data['start_date'] ?? $data['service_date'] ?? null;
            $endRaw = $data['end_date'] ?? null;
            $this->validateProjectDates($data['project_uuid'] ?? null, $startRaw, $endRaw);

            $dailyRoute = $this->buildDailyRoute($data['daily_route'] ?? null, $data['routes'] ?? null);

            $record = ServiceDeliveryControlSheet::create([
                'official_name_and_surname' => $data['official_name_and_surname'] ?? null,
                'service_date' => $startRaw,
                'start_date' => $startRaw,
                'end_date' => $endRaw,
                'daily_route' => $dailyRoute,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'total_hours' => $data['total_hours'] ?? null,
                'starting_kilometer' => $data['starting_kilometer'] ?? null,
                'ending_kilometer' => $data['ending_kilometer'] ?? null,
                'number_of_tolls' => $data['number_of_tolls'] ?? null,
                'total_toll_value' => $data['total_toll_value'] ?? null,
                'type_of_control_sheet' => $type,
                'is_active' => $data['is_active'] ?? true,
                'company_uuid' => $data['company_uuid'],
                'project_uuid' => $data['project_uuid'] ?? null,
            ]);

            $this->createControlForSheet($record, $data, $type, false);
            $this->syncRoutes($record, $data['routes'] ?? null);

            // Si daily_route no venía pero sí routes, ya quedó construido. Si no hay routes ni daily_route, se deja null (compatible).
            // Multi-día: generar hijos diarios
            $startDate = $startRaw ? Carbon::parse($startRaw) : null;
            $endDate = $endRaw ? Carbon::parse($endRaw) : null;

            if ($startDate && $endDate && $endDate->greaterThan($startDate)) {
                $currentDate = $startDate->copy()->addDay();
                $parentRoutes = $record->routes()->get();

                while (! $currentDate->greaterThan($endDate)) {
                    $child = ServiceDeliveryControlSheet::create([
                        'official_name_and_surname' => $data['official_name_and_surname'] ?? null,
                        'service_date' => $currentDate->toDateString(),
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                        'parent_uuid' => $record->uuid,
                        'daily_route' => $dailyRoute,
                        'type_of_control_sheet' => $type,
                        'is_active' => true,
                        'company_uuid' => $data['company_uuid'],
                        'project_uuid' => $data['project_uuid'] ?? null,
                    ]);

                    $this->createControlForSheet($child, $data, $type, true);

                    foreach ($parentRoutes as $pr) {
                        ServiceDeliveryControlSheetRoute::create([
                            'uuid' => (string) Str::uuid(),
                            'service_delivery_control_sheet_uuid' => $child->uuid,
                            'order_index' => $pr->order_index,
                            'origin' => $pr->origin,
                            'destination' => $pr->destination,
                            'is_active' => true,
                        ]);
                    }

                    $currentDate->addDay();
                }
            }

            return $record->fresh(['project', 'routes', 'children']);
        });
    }

    public function updateServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $type = isset($data['type_of_control_sheet']) ? $this->normalizeType($data['type_of_control_sheet']) : $record->type_of_control_sheet;

            $newProject = $data['project_uuid'] ?? $record->project_uuid;
            $newStart = $data['start_date'] ?? ($record->start_date ? Carbon::parse($record->start_date)->toDateString() : null);
            $newEnd = $data['end_date'] ?? ($record->end_date ? Carbon::parse($record->end_date)->toDateString() : null);
            $this->validateProjectDates($newProject, $newStart, $newEnd);

            $dailyRoute = $this->buildDailyRoute(
                $data['daily_route'] ?? $record->daily_route,
                $data['routes'] ?? null
            );

            $record->update([
                'official_name_and_surname' => $data['official_name_and_surname'] ?? $record->official_name_and_surname,
                'service_date' => $data['service_date'] ?? $record->service_date,
                'daily_route' => $dailyRoute,
                'start_time' => $data['start_time'] ?? $record->start_time,
                'end_time' => $data['end_time'] ?? $record->end_time,
                'total_hours' => $data['total_hours'] ?? $record->total_hours,
                'starting_kilometer' => $data['starting_kilometer'] ?? $record->starting_kilometer,
                'ending_kilometer' => $data['ending_kilometer'] ?? $record->ending_kilometer,
                'number_of_tolls' => $data['number_of_tolls'] ?? $record->number_of_tolls,
                'total_toll_value' => $data['total_toll_value'] ?? $record->total_toll_value,
                'type_of_control_sheet' => $type,
                'is_active' => $data['is_active'] ?? $record->is_active,
                'project_uuid' => $newProject,
            ]);

            if (array_key_exists('routes', $data)) {
                $this->syncRoutes($record, $data['routes']);
            }

            return $record->fresh(['project', 'routes']);
        });
    }

    public function deleteServiceDeliveryControlSheet(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ServiceDeliveryControlSheetService@deleteServiceDeliveryControlSheet: '.$e->getMessage());
            throw $e;
        }
    }

    public function startServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'start_time' => $data['start_time'] ?? $record->start_time,
                'starting_kilometer' => $data['starting_kilometer'] ?? $record->starting_kilometer,
            ]);

            if (! empty($data['fuec_uuid'])) {
                if (! $this->isExternalType($record->type_of_control_sheet) && $record->internalControl) {
                    $this->serviceInternalControlService->updateServiceInternalControl($record->internalControl->uuid, [
                        'fuec_uuid' => $data['fuec_uuid'],
                    ]);
                }
            }

            return $record->fresh();
        });
    }

    public function closeServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $pendingChildren = ServiceDeliveryControlSheet::query()
                ->where('parent_uuid', $record->uuid)
                ->where('is_active', true)
                ->count();

            if ($pendingChildren > 0) {
                throw ValidationException::withMessages([
                    'close' => "No se puede cerrar el servicio hasta completar todas las planillas diarias ({$pendingChildren} pendiente(s)).",
                ]);
            }

            $record->update([
                'end_time' => $data['end_time'] ?? $record->end_time,
                'ending_kilometer' => $data['ending_kilometer'] ?? $record->ending_kilometer,
                'total_hours' => $data['total_hours'] ?? $record->total_hours,
                'number_of_tolls' => $data['number_of_tolls'] ?? $record->number_of_tolls,
                'total_toll_value' => $data['total_toll_value'] ?? $record->total_toll_value,
                'is_active' => false,
            ]);

            if (! empty($data['funcionario_signature'])) {
                $this->signatureService->store([
                    'signature' => $data['funcionario_signature'],
                    'entity_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                    'entity_id' => $record->id,
                    'company_uuid' => $record->company_uuid,
                ]);
            }

            if (! empty($data['conductor_signature'])) {
                $this->signatureService->store([
                    'signature' => $data['conductor_signature'],
                    'entity_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                    'entity_id' => $record->id,
                    'company_uuid' => $record->company_uuid,
                ]);
            }

            if (! empty($record->parent_uuid)) {
                $remainingChildren = ServiceDeliveryControlSheet::query()
                    ->where('parent_uuid', $record->parent_uuid)
                    ->where('is_active', true)
                    ->count();

                if ($remainingChildren === 0) {
                    ServiceDeliveryControlSheet::query()
                        ->where('uuid', $record->parent_uuid)
                        ->update(['is_active' => false]);
                }
            }

            return $record->fresh();
        });
    }
}
