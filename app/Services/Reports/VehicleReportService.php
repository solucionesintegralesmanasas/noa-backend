<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Vehicle;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Servicio del reporte de vehículos.
 *
 * Un solo filtro activo a la vez (filter_type + filter_value) para
 * mantener consultas acotadas y de bajo consumo.
 */
class VehicleReportService extends BaseService
{
    public const FILTER_AFFILIATE = 'affiliate';

    public const FILTER_OPERATION_CARD = 'operation_card';

    public const FILTER_DOCUMENT = 'document';

    public const FILTER_PROJECT = 'project';

    public const FILTER_MAINTENANCE = 'maintenance';

    public const FILTER_DRIVER = 'driver';

    public const FILTER_AGREEMENT = 'agreement';

    public const FILTER_TYPES = [
        self::FILTER_AFFILIATE,
        self::FILTER_OPERATION_CARD,
        self::FILTER_DOCUMENT,
        self::FILTER_PROJECT,
        self::FILTER_MAINTENANCE,
        self::FILTER_DRIVER,
        self::FILTER_AGREEMENT,
    ];

    public const DOCUMENT_TYPES = ['SOAT', 'RCC', 'RCE', 'RTM'];

    protected function getModelInstance(): Model
    {
        return new Vehicle;
    }

    /**
     * Construye la consulta base con relaciones mínimas y columnas recortadas.
     */
    public function baseQuery(array $filtros = []): Builder
    {
        $query = Vehicle::query()->select([
            'vehicles.uuid',
            'vehicles.company_uuid',
            'vehicles.third_party_uuid',
            'vehicles.vehicle_license_plate',
            'vehicles.model',
            'vehicles.body_type',
            'vehicles.type_of_service',
            'vehicles.vehicle_class_uuid',
            'vehicles.is_active',
        ])->with([
            'vehicleClass:uuid,description',
            'thirdParty:uuid,first_name,last_name,company_name,document_number',
            'vehicleDocuments:uuid,vehicle_uuid,document_type,expiry_date,status',
            'operationCards:uuid,vehicle_uuid,operating_card_number,expiration_date,status',
            'businessCollaborationAgreements:vehicle_uuid,contracting_entity_name,expiry_date,status',
            'driverVehicleAssignments:vehicle_uuid,project_uuid,third_party_uuid,is_active',
            'driverVehicleAssignments.project:uuid,project_name',
            'driverVehicleAssignments.thirdParty:uuid,first_name,last_name,company_name',
            'maintenance:vehicle_uuid,maintenance_date,next_maintenance_date,status',
        ]);

        if (! empty($filtros['company_uuid'])) {
            $query->where('vehicles.company_uuid', $filtros['company_uuid']);
        }

        $this->applyReportFilter($query, $filtros);

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $query->where(function ($q) use ($search) {
                $q->where('vehicles.vehicle_license_plate', 'like', "%{$search}%")
                    ->orWhere('vehicles.model', 'like', "%{$search}%");
            });
        }

        $query->orderBy('vehicles.vehicle_license_plate', 'asc');

        return $query;
    }

    /**
     * Aplica el único filtro activo del reporte.
     */
    protected function applyReportFilter(Builder $query, array $filtros): void
    {
        $type = $filtros['filter_type'] ?? null;
        if (! $type || ! in_array($type, self::FILTER_TYPES, true)) {
            return;
        }

        switch ($type) {
            case self::FILTER_AFFILIATE:
                if (! empty($filtros['third_party_uuid'])) {
                    $uuid = $filtros['third_party_uuid'];
                    $query->where(function ($q) use ($uuid) {
                        $q->where('vehicles.third_party_uuid', $uuid)
                            ->orWhereHas('owners', fn ($oq) => $oq->where('third_party_uuid', $uuid));
                    });
                }
                break;

            case self::FILTER_DRIVER:
                if (! empty($filtros['third_party_uuid'])) {
                    $uuid = $filtros['third_party_uuid'];
                    $query->whereHas('driverVehicleAssignments', fn ($q) => $q->where('third_party_uuid', $uuid)->where('is_active', 1));
                }
                break;

            case self::FILTER_PROJECT:
                if (! empty($filtros['project_uuid'])) {
                    $uuid = $filtros['project_uuid'];
                    $query->whereHas('driverVehicleAssignments', fn ($q) => $q->where('project_uuid', $uuid)->where('is_active', 1));
                }
                break;

            case self::FILTER_OPERATION_CARD:
                $this->applyOperationCardFilter($query, $filtros);
                break;

            case self::FILTER_DOCUMENT:
                $this->applyDocumentFilter($query, $filtros);
                break;

            case self::FILTER_MAINTENANCE:
                $this->applyMaintenanceFilter($query, $filtros);
                break;

            case self::FILTER_AGREEMENT:
                if (! empty($filtros['contracting_entity_name'])) {
                    $name = trim((string) $filtros['contracting_entity_name']);
                    $query->whereHas('businessCollaborationAgreements', fn ($q) => $q->where('contracting_entity_name', $name));
                }
                break;
        }
    }

    /**
     * Conductores (personas naturales) con asignación activa a vehículos.
     * Solo estos producen resultados en el filtro de conductor.
     */
    public function distinctDrivers(?string $companyUuid): Collection
    {
        $uuids = \App\Models\ProjectDriverVehicle::query()
            ->where('is_active', 1)
            ->when($companyUuid, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('vehicles.company_uuid', $companyUuid)))
            ->distinct()
            ->pluck('third_party_uuid');

        return \App\Models\ThirdParty::query()
            ->whereIn('uuid', $uuids)
            ->where('person_type', 'NATURAL')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get(['uuid', 'first_name', 'last_name', 'document_number']);
    }

    /**
     * Empresas distintas con tarjeta de operación (para el selector del filtro).
     */
    public function distinctAffiliatedCompanies(?string $companyUuid): Collection
    {
        $query = \App\Models\OperationCard::query()
            ->whereNotNull('affiliated_company')
            ->where('affiliated_company', '<>', '');
        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->distinct()->orderBy('affiliated_company')->limit(500)->pluck('affiliated_company');
    }

    /**
     * Convenios distintos (para el selector del filtro).
     */
    public function distinctAgreementNames(?string $companyUuid): Collection
    {
        $query = \App\Models\BusinessCollaborationAgreement::query()
            ->whereNotNull('contracting_entity_name')
            ->where('contracting_entity_name', '<>', '');
        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->distinct()->orderBy('contracting_entity_name')->limit(500)->pluck('contracting_entity_name');
    }

    protected function applyOperationCardFilter(Builder $query, array $filtros): void
    {
        $filtrosDeTarjeta = ['affiliated_company', 'operating_card_number', 'operation_card_status', 'operation_card_expiry_from', 'operation_card_expiry_to'];
        if (array_filter(array_intersect_key($filtros, array_flip($filtrosDeTarjeta)), fn ($v) => $v !== null && $v !== '')) {
            // Un vehículo particular no tiene tarjeta de operación: nunca coincide con estos filtros.
            $query->where('vehicles.type_of_service', '!=', Vehicle::SERVICIO_PARTICULAR);
        }

        if (! empty($filtros['affiliated_company'])) {
            $company = trim((string) $filtros['affiliated_company']);
            $query->whereHas('operationCards', fn ($q) => $q->where('affiliated_company', $company));
        }

        if (! empty($filtros['operating_card_number'])) {
            $num = trim((string) $filtros['operating_card_number']);
            $query->whereHas('operationCards', fn ($q) => $q->where('operating_card_number', 'like', "%{$num}%"));
        }

        if (! empty($filtros['operation_card_status'])) {
            $status = $filtros['operation_card_status'];
            if (in_array($status, ['0', '1', 0, 1, true, false], true)) {
                $query->whereHas('operationCards', fn ($q) => $q->where('status', (bool) $status));
            } elseif (in_array($status, ['vigente', 'vencida', 'por_vencer'], true)) {
                $this->filterRelationByExpiry($query, 'operationCards', 'expiration_date', $status);
            }
        }

        if (! empty($filtros['operation_card_expiry_from']) || ! empty($filtros['operation_card_expiry_to'])) {
            $from = $filtros['operation_card_expiry_from'] ?? null;
            $to = $filtros['operation_card_expiry_to'] ?? null;
            $query->whereHas('operationCards', function ($q) use ($from, $to) {
                if ($from) {
                    $q->where('expiration_date', '>=', $from);
                }
                if ($to) {
                    $q->where('expiration_date', '<=', $to);
                }
            });
        }
    }

    protected function applyDocumentFilter(Builder $query, array $filtros): void
    {
        $docType = strtoupper(trim((string) ($filtros['document_type'] ?? '')));
        $docStatus = $filtros['doc_status'] ?? null;

        if ($docType && ! in_array($docType, self::DOCUMENT_TYPES, true)) {
            return;
        }

        if (in_array($docType, Vehicle::DOCUMENTOS_NO_APLICAN_A_PARTICULARES, true)) {
            $query->where('vehicles.type_of_service', '!=', Vehicle::SERVICIO_PARTICULAR);
        }

        if ($docType || $docStatus) {
            $query->whereHas('vehicleDocuments', function ($q) use ($docType, $docStatus) {
                if ($docType) {
                    $q->where('document_type', $docType);
                }
                if (in_array($docStatus, ['vigente', 'vencido', 'por_vencer'], true)) {
                    $today = Carbon::today()->toDateString();
                    if ($docStatus === 'vencido') {
                        $q->where('expiry_date', '<', $today);
                    } elseif ($docStatus === 'vigente') {
                        $q->where('expiry_date', '>=', $today);
                    } else {
                        $q->where('expiry_date', '>=', $today)
                            ->where('expiry_date', '<=', Carbon::today()->addDays(30)->toDateString());
                    }
                }
            });
        }
    }

    protected function applyMaintenanceFilter(Builder $query, array $filtros): void
    {
        $status = $filtros['maintenance_status'] ?? null;

        if ($status) {
            $query->whereHas('maintenance', function ($q) use ($status, $filtros) {
                if ($status === 'vencido') {
                    $q->whereNotNull('next_maintenance_date')
                        ->where('next_maintenance_date', '<', Carbon::today()->toDateString());
                } elseif ($status === 'proximo_30d') {
                    $q->where('next_maintenance_date', '>=', Carbon::today()->toDateString())
                        ->where('next_maintenance_date', '<=', Carbon::today()->addDays(30)->toDateString());
                } elseif ($status === 'pendiente') {
                    $q->where('status', 'like', '%pendiente%');
                }
                if (! empty($filtros['maintenance_from'])) {
                    $q->where('maintenance_date', '>=', $filtros['maintenance_from']);
                }
                if (! empty($filtros['maintenance_to'])) {
                    $q->where('maintenance_date', '<=', $filtros['maintenance_to']);
                }
            });
        } elseif (! empty($filtros['maintenance_from']) || ! empty($filtros['maintenance_to'])) {
            $from = $filtros['maintenance_from'] ?? null;
            $to = $filtros['maintenance_to'] ?? null;
            $query->whereHas('maintenance', function ($q) use ($from, $to) {
                if ($from) {
                    $q->where('maintenance_date', '>=', $from);
                }
                if ($to) {
                    $q->where('maintenance_date', '<=', $to);
                }
            });
        }
    }

    protected function filterRelationByExpiry(Builder $query, string $relation, string $column, string $status): void
    {
        $query->whereHas($relation, function ($q) use ($column, $status) {
            $today = Carbon::today()->toDateString();
            if ($status === 'vencida' || $status === 'vencido') {
                $q->where($column, '<', $today);
            } elseif ($status === 'vigente') {
                $q->where($column, '>=', $today);
            } else {
                $q->where($column, '>=', $today)
                    ->where($column, '<=', Carbon::today()->addDays(30)->toDateString());
            }
        });
    }

    /**
     * Listado paginado con filas planas listas para la tabla.
     */
    public function paginateReport(array $filtros, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);
        $paginator = $this->baseQuery($filtros)->paginate($perPage, ['*'], 'page', $page);
        $paginator->getCollection()->transform(fn ($vehicle) => $this->mapRow($vehicle));

        return $paginator;
    }

    /**
     * Colección plana para Excel/PDF (tope 1000).
     */
    public function allForExport(array $filtros, int $limit = 1000): Collection
    {
        return $this->baseQuery($filtros)->limit($limit)->get()->map(fn ($vehicle) => $this->mapRow($vehicle));
    }

    /**
     * Mapea un vehículo a la fila plana del reporte.
     */
    public function mapRow(Vehicle $vehicle): array
    {
        // Por tipo: vencimiento y uuid del documento más reciente (el frontend enlaza a su edición).
        $docs = [];
        $docUuids = [];
        foreach ($vehicle->relationLoaded('vehicleDocuments') ? $vehicle->vehicleDocuments : collect() as $doc) {
            $key = strtoupper((string) $doc->document_type);
            if (! $vehicle->admiteTipoDocumento($key)) {
                continue; // RCC/RCE históricos de un vehículo hoy particular: no aplican
            }
            if (! isset($docs[$key]) || $doc->expiry_date > $docs[$key]) {
                $docs[$key] = $doc->expiry_date ? Carbon::parse($doc->expiry_date)->toDateString() : null;
                $docUuids[$key] = $doc->uuid;
            }
        }

        $card = null;
        if ($vehicle->requiereTarjetaOperacion() && $vehicle->relationLoaded('operationCards') && $vehicle->operationCards->isNotEmpty()) {
            $card = $vehicle->operationCards->sortByDesc('expiration_date')->first();
        }

        $agreement = null;
        if ($vehicle->relationLoaded('businessCollaborationAgreements') && $vehicle->businessCollaborationAgreements->isNotEmpty()) {
            $agreement = $vehicle->businessCollaborationAgreements
                ->filter(fn ($a) => (bool) $a->status)
                ->sortByDesc('expiry_date')
                ->first() ?? $vehicle->businessCollaborationAgreements->sortByDesc('expiry_date')->first();
        }

        $assignment = null;
        if ($vehicle->relationLoaded('driverVehicleAssignments') && $vehicle->driverVehicleAssignments->isNotEmpty()) {
            $assignment = $vehicle->driverVehicleAssignments->firstWhere('is_active', 1)
                ?? $vehicle->driverVehicleAssignments->first();
        }

        $affiliate = $vehicle->thirdParty;
        $driver = $assignment && $assignment->relationLoaded('thirdParty') ? $assignment->thirdParty : null;
        $project = $assignment && $assignment->relationLoaded('project') ? $assignment->project : null;

        $affiliateName = $affiliate
            ? ($affiliate->company_name ?: trim(($affiliate->first_name ?? '').' '.($affiliate->last_name ?? '')))
            : null;

        return [
            'uuid' => $vehicle->uuid,
            'vehicle_license_plate' => $vehicle->vehicle_license_plate,
            'model' => $vehicle->model,
            'vehicle_class' => $vehicle->vehicleClass?->description,
            'body_type' => $vehicle->body_type,
            'type_of_service' => $vehicle->type_of_service,
            'modality_label' => $vehicle->type_of_service === 'PUBLICO' ? 'Público' : 'Particular',
            // Indicador para mostrar "No aplica" en pólizas RCC/RCE y tarjeta de operación (Excel, PDF y pantalla).
            'es_particular' => $vehicle->esParticular(),
            'soat_expiry' => $docs['SOAT'] ?? null,
            'rcc_expiry' => $docs['RCC'] ?? null,
            'rce_expiry' => $docs['RCE'] ?? null,
            'rtm_expiry' => $docs['RTM'] ?? null,
            'soat_uuid' => $docUuids['SOAT'] ?? null,
            'rcc_uuid' => $docUuids['RCC'] ?? null,
            'rce_uuid' => $docUuids['RCE'] ?? null,
            'rtm_uuid' => $docUuids['RTM'] ?? null,
            'operation_card_uuid' => $card?->uuid,
            'operation_card_number' => $card?->operating_card_number,
            'operation_card_expiry' => $card?->expiration_date ? Carbon::parse($card->expiration_date)->toDateString() : null,
            'agreement_name' => $agreement?->contracting_entity_name,
            'agreement_expiry' => $agreement?->expiry_date ? Carbon::parse($agreement->expiry_date)->toDateString() : null,
            'affiliate_name' => $affiliateName,
            'driver_name' => $driver
                ? ($driver->company_name ?: trim(($driver->first_name ?? '').' '.($driver->last_name ?? '')))
                : null,
            'project_name' => $project?->project_name,
        ];
    }
}
