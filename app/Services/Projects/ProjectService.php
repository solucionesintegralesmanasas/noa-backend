<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectDriverVehicle;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Servicio de negocio para la gestión de proyectos.
 *
 * Un proyecto es una entidad maestra que agrupa a varios conductores (terceros)
 * y a varios vehículos mediante tablas pivote.
 *
 * @author   Darwin Montes
 * @version  1.0.0
 * @since    1.0.0
 * @created  2026-09-01
 */
class ProjectService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['project_name', 'purchase_order'];

    /**
     * Obtiene una instancia del modelo asociado al servicio.
     */
    protected function getModelInstance(): Model
    {
        return new Project;
    }

    /**
     * Obtiene el catálogo de proyectos de la empresa con conteo de asignaciones.
     *
     * @return Collection<int, Project>
     */
    public function getAllProjects(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()
            ->withCount(['driverVehicleAssignments as assignments_count' => fn ($q) => $q->active()])
            ->orderBy('project_name');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid')
                ?? Auth::user()?->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->forThirdParty($thirdPartyUuid);
        }

        return $query->get([
            'uuid',
            'company_uuid',
            'project_name',
            'start_date',
            'completion_date',
            'project_value',
            'purchase_order',
        ]);
    }

    /**
     * Obtiene los proyectos paginados de la empresa, opcionalmente filtrados
     * por un conductor (tercero), con conteo de conductores y vehículos.
     */
    public function getProjectsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid')
                ?? Auth::user()?->companies()->first()?->uuid;
        }

        $query = $this->query()
            ->withCount([
                'driverVehicleAssignments as assignments_count' => fn ($q) => $q->active(),
                'media as purchase_order_count' => fn ($q) => $q->where('collection_name', 'ORDEN_COMPRA'),
            ]);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->forThirdParty($thirdPartyUuid);
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

        $paginator = $query->paginate($perPage, [
            'uuid',
            'company_uuid',
            'project_name',
            'start_date',
            'completion_date',
            'project_value',
            'purchase_order',
        ], 'page', $page);

        $paginator->getCollection()->transform(fn (Project $project) => [
            'uuid' => $project->uuid,
            'company_uuid' => $project->company_uuid,
            'project_name' => $project->project_name,
            'start_date' => $project->start_date?->toDateString(),
            'completion_date' => $project->completion_date?->toDateString(),
            'project_value' => (float) $project->project_value,
            'purchase_order' => $project->purchase_order,
            'has_purchase_order' => (int) ($project->purchase_order_count ?? 0) > 0,
            'assignments_count' => (int) $project->assignments_count,
            'created_at' => $project->created_at?->toDateTimeString(),
        ]);

        return $paginator;
    }

    /**
     * Obtiene un proyecto por su UUID con sus asignaciones
     * conductor → vehículo (relación 1:1) para el detalle.
     */
    public function getProjectByUuid(string $uuid): ?Model
    {
        $record = $this->findByUuid($uuid);

        if (! $record) {
            return null;
        }

        $assignments = $record->driverVehicleAssignments()
            ->with([
                'thirdParty' => fn ($q) => $q->select(
                    'uuid',
                    'first_name',
                    'last_name',
                    'company_name',
                    'document_number',
                    'is_driver',
                    'is_active'
                ),
                'vehicle' => fn ($q) => $q->select(
                    'uuid',
                    'vehicle_license_plate',
                    'internal_number',
                    'model',
                    'is_active'
                ),
            ])
            ->orderByRaw('is_active DESC, id ASC')
            ->get();

        $assignedValuer = fn (ProjectDriverVehicle $assignment) => [
            'uuid' => $assignment->uuid,
            'third_party_uuid' => $assignment->third_party_uuid,
            'vehicle_uuid' => $assignment->vehicle_uuid,
            'is_active' => (bool) $assignment->is_active,
            'conductor' => [
                'uuid' => $assignment->thirdParty?->uuid,
                'first_name' => $assignment->thirdParty?->first_name,
                'last_name' => $assignment->thirdParty?->last_name,
                'company_name' => $assignment->thirdParty?->company_name,
                'document_number' => $assignment->thirdParty?->document_number,
                'is_driver' => $assignment->thirdParty?->is_driver,
                'is_active' => $assignment->thirdParty?->is_active,
            ],
            'vehicle' => [
                'uuid' => $assignment->vehicle?->uuid,
                'vehicle_license_plate' => $assignment->vehicle?->vehicle_license_plate,
                'internal_number' => $assignment->vehicle?->internal_number,
                'model' => $assignment->vehicle?->model,
                'is_active' => $assignment->vehicle?->is_active,
            ],
        ];

        $record->setAttribute('assignments', $assignments->map($assignedValuer)->values());
        $record->setAttribute('assignments_count', $assignments->where('is_active', true)->count());

        return $record;
    }

    /**
     * Crea un nuevo proyecto y sus asignaciones de conductores y vehículos.
     */
    public function createProject(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $project = Project::create([
                'company_uuid' => $data['company_uuid'],
                'project_name' => $data['project_name'],
                'start_date' => $data['start_date'],
                'completion_date' => $data['completion_date'],
                'project_value' => $data['project_value'] ?? 0,
                'purchase_order' => $data['purchase_order'] ?? null,
            ]);

            $this->syncProjectAssignments($project, $data);

            $this->handlePurchaseOrderFile($project, $data);

            return $this->getProjectByUuid($project->uuid);
        });
    }

    /**
     * Actualiza un proyecto y sincroniza sus asignaciones conductor-vehículo.
     */
    public function updateProject(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            if (! $record) {
                throw new \RuntimeException('Project not found.');
            }

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'project_name' => $data['project_name'] ?? $record->project_name,
                'start_date' => $data['start_date'] ?? $record->start_date,
                'completion_date' => $data['completion_date'] ?? $record->completion_date,
                'project_value' => $data['project_value'] ?? $record->project_value,
                'purchase_order' => $data['purchase_order'] ?? $record->purchase_order,
            ]);

            $this->syncProjectAssignments($record, $data);

            $this->handlePurchaseOrderFile($record, $data);

            return $this->getProjectByUuid($record->uuid);
        });
    }

    /**
     * Gestiona el PDF de la orden de compra del proyecto.
     *
     * - Si llega un archivo nuevo, se guarda (la colección es singleFile,
     *   por lo que Spatie reemplaza automáticamente el anterior).
     * - Si llega la bandera remove_purchase_order y no hay archivo nuevo,
     *   se elimina el PDF existente.
     *
     * @param array<string, mixed> $data
     */
    private function handlePurchaseOrderFile(Project $project, array $data): void
    {
        $file = $data['purchase_order_file'] ?? null;
        $remove = filter_var($data['remove_purchase_order'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($file) {
            $project->addFile($file, 'ORDEN_COMPRA');
        } elseif ($remove) {
            $project->clearFilesByType('ORDEN_COMPRA');
        }
    }

    /**
     * Sincroniza las asignaciones conductor → vehículo del proyecto
     * (relación 1:1) según el payload recibido.
     *
     * Cada fila de asignación es [third_party_uuid => vehicle_uuid, is_active?].
     * - Las filas que ya no llegan en el payload se ELIMINAN (fueron quitadas
     *   del formulario).
     * - Las que llegan se actualizan/crean; si is_active es false se marcan como
     *   retiradas, conservando el historial sin borrarlas.
     */
    private function syncProjectAssignments(Project $project, array $data): void
    {
        if (! array_key_exists('assignments', $data)) {
            return;
        }

        $clean = [];

        foreach (($data['assignments'] ?? []) as $assignment) {
            $driver = (string) ($assignment['third_party_uuid'] ?? '');
            $vehicle = (string) ($assignment['vehicle_uuid'] ?? '');

            if ($driver !== '' && $vehicle !== '') {
                $clean[$driver] = [
                    'vehicle_uuid' => $vehicle,
                    'is_active' => (bool) filter_var($assignment['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        // En lugar de borrar asignaciones que se retiran del formulario,
        // marcarlas como inactivas (is_active = false) para conservar el historial del conductor y vehículo
        ProjectDriverVehicle::where('project_uuid', $project->uuid)
            ->whereNotIn('third_party_uuid', array_keys($clean))
            ->update(['is_active' => false]);

        $existing = ProjectDriverVehicle::where('project_uuid', $project->uuid)
            ->get();

        foreach ($clean as $driverUuid => $config) {
            // Verificar si ya existe exactamente esta asignación de conductor y vehículo
            $exact = $existing->first(fn ($r) => $r->third_party_uuid === $driverUuid && $r->vehicle_uuid === $config['vehicle_uuid']);

            if ($exact) {
                if ((bool) $exact->is_active !== $config['is_active']) {
                    $exact->update(['is_active' => $config['is_active']]);
                }
            } else {
                // Si el conductor tenía asignado otro vehículo activo en este proyecto,
                // marcar la asignación anterior como inactiva para conservar el historial
                $previousAssignments = $existing->where('third_party_uuid', $driverUuid)->where('is_active', true);
                foreach ($previousAssignments as $prev) {
                    $prev->update(['is_active' => false]);
                }

                // Crear la nueva asignación
                ProjectDriverVehicle::create([
                    'project_uuid' => $project->uuid,
                    'third_party_uuid' => $driverUuid,
                    'vehicle_uuid' => $config['vehicle_uuid'],
                    'is_active' => $config['is_active'],
                ]);
            }
        }
    }

    /**
     * Elimina un proyecto (sus asignaciones pivote se eliminan en cascada).
     */
    public function deleteProject(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException('Failed to delete Project.');
        }
    }
}