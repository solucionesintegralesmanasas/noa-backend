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

    public const FILTER_BRANCH = 'branch';

    public const FILTER_TYPES = [
        self::FILTER_AFFILIATE,
        self::FILTER_OPERATION_CARD,
        self::FILTER_DOCUMENT,
        self::FILTER_PROJECT,
        self::FILTER_MAINTENANCE,
        self::FILTER_DRIVER,
        self::FILTER_AGREEMENT,
        self::FILTER_BRANCH,
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
            'operationCards:uuid,vehicle_uuid,operating_card_number,expiration_date,status,affiliated_company,runt_status',
            'branch:branches.uuid,branches.name',
            'businessCollaborationAgreements:vehicle_uuid,contracting_entity_name,expiry_date,status',
            'driverVehicleAssignments:vehicle_uuid,project_uuid,third_party_uuid,is_active',
            'driverVehicleAssignments.project:uuid,project_name',
            'driverVehicleAssignments.thirdParty:uuid,first_name,last_name,company_name',
            'maintenance:vehicle_uuid,maintenance_date,next_maintenance_date,status',
        ]);

        if (! empty($filtros['company_uuid'])) {
            $query->where('vehicles.company_uuid', $filtros['company_uuid']);
        }

        // Excluir vehículos inactivos de los reportes y del calendario (solo flota activa)
        if (empty($filtros['include_inactive'])) {
            $query->where('vehicles.is_active', true);
        }

        // Filtro transversal por sede
        if (! empty($filtros['branch_uuid'])) {
            $branchUuid = $filtros['branch_uuid'];
            $query->whereHas('vehicleBranches', function (Builder $q) use ($branchUuid) {
                $q->where('vehicles_branches.branch_uuid', $branchUuid)
                    ->where('vehicles_branches.is_active', 1);
            });
        }

        // Acotación opcional por mes y año (usada en reportes y exportaciones del calendario)
        if (! empty($filtros['month']) && ! empty($filtros['year'])) {
            $m = (int) $filtros['month'];
            $y = (int) $filtros['year'];
            $start = Carbon::createFromDate($y, $m, 1)->startOfMonth()->toDateString();
            $end = Carbon::createFromDate($y, $m, 1)->endOfMonth()->toDateString();
            $query->where(function (Builder $sub) use ($start, $end) {
                $sub->whereHas('vehicleDocuments', fn ($d) => $d->whereBetween('expiry_date', [$start, $end]))
                    ->orWhereHas('operationCards', fn ($c) => $c->whereBetween('expiration_date', [$start, $end]))
                    ->orWhereHas('businessCollaborationAgreements', fn ($a) => $a->whereBetween('expiry_date', [$start, $end]));
            });
        }

        // Filtro transversal por propiedad (propios vs terceros/afiliados)
        if (! empty($filtros['ownership_type']) && in_array($filtros['ownership_type'], ['propios', 'terceros'], true)) {
            $this->applyOwnershipFilter($query, $filtros['ownership_type'], $filtros['company_uuid'] ?? null);
        }

        // Filtro transversal por modalidad / tipo de servicio (PUBLICO vs PARTICULAR)
        if (! empty($filtros['service_type'])) {
            $svc = strtoupper(trim((string) $filtros['service_type']));
            if ($svc === Vehicle::SERVICIO_PARTICULAR) {
                $query->where('vehicles.type_of_service', Vehicle::SERVICIO_PARTICULAR);
            } elseif ($svc === 'PUBLICO') {
                $query->where('vehicles.type_of_service', '!=', Vehicle::SERVICIO_PARTICULAR);
            }
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
                // Si se combina con filtro de documento, tarjeta o convenio
                if (! empty($filtros['document_type'])) {
                    $this->applyDocumentFilter($query, $filtros);
                }
                if (! empty($filtros['operating_card_number']) || ! empty($filtros['operation_card_status'])) {
                    $this->applyOperationCardFilter($query, $filtros);
                }
                if (! empty($filtros['agreement_status'])) {
                    $this->applyAgreementFilter($query, $filtros);
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
                $this->applyAgreementFilter($query, $filtros);
                break;

            case self::FILTER_BRANCH:
                // Aplicado de forma transversal y unificada en baseQuery
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
            ->whereHas('vehicle', fn ($v) => $v->where('vehicles.is_active', true)
                ->when($companyUuid, fn ($w) => $w->where('vehicles.company_uuid', $companyUuid)))
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
            ->where('affiliated_company', '<>', '')
            ->whereHas('vehicle', fn ($v) => $v->where('vehicles.is_active', true));
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
            ->where('contracting_entity_name', '<>', '')
            ->whereHas('vehicle', fn ($v) => $v->where('vehicles.is_active', true));
        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->distinct()->orderBy('contracting_entity_name')->limit(500)->pluck('contracting_entity_name');
    }

    /**
     * Sedes operativas activas de la empresa (para el selector del filtro).
     */
    public function distinctBranches(?string $companyUuid): Collection
    {
        $query = \App\Models\Branch::query()
            ->where('status', 1);
        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->orderBy('name')->limit(500)->get(['uuid', 'name']);
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
            } else {
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
        $rawDocType = trim((string) ($filtros['document_type'] ?? ''));
        $docType = strtoupper($rawDocType);
        $docStatus = $filtros['doc_status'] ?? null;

        $isAll = $docType === '' || in_array($docType, ['TODOS', 'ALL'], true);

        if (! $isAll && ! in_array($docType, self::DOCUMENT_TYPES, true)) {
            return;
        }

        if (! $isAll && in_array($docType, Vehicle::DOCUMENTOS_NO_APLICAN_A_PARTICULARES, true)) {
            $query->where('vehicles.type_of_service', '!=', Vehicle::SERVICIO_PARTICULAR);
        }

        if ($docType || $docStatus) {
            $query->whereHas('vehicleDocuments', function ($q) use ($docType, $docStatus, $isAll) {
                // Excluir documentos reemplazados
                $q->where(function ($sq) {
                    $sq->whereNull('status')->orWhere('status', '!=', 'INACTIVA');
                });

                if (! $isAll) {
                    $q->where('document_type', $docType);
                } else {
                    // Para "Todos": solo documentos que aplican según tipo de servicio
                    $q->where(function ($subQ) {
                        $subQ->whereIn('document_type', ['SOAT', 'RTM'])
                            ->orWhere(function ($pubQ) {
                                $pubQ->whereIn('document_type', ['RCC', 'RCE'])
                                    ->whereHas('vehicle', fn ($v) => $v->where('type_of_service', '!=', Vehicle::SERVICIO_PARTICULAR));
                            });
                    });
                }

                if ($docStatus) {
                    $this->applyDateStatusCondition($q, 'expiry_date', $docStatus);
                }
            });
        }
    }

    protected function applyAgreementFilter(Builder $query, array $filtros): void
    {
        $name = trim((string) ($filtros['contracting_entity_name'] ?? ''));
        $status = $filtros['agreement_status'] ?? $filtros['doc_status'] ?? null;
        $isAllAgreements = $name === '' || in_array(strtoupper($name), ['TODOS', 'ALL'], true);

        if ($status) {
            $query->whereHas('businessCollaborationAgreements', function ($q) use ($name, $status, $isAllAgreements) {
                if (! $isAllAgreements) {
                    $q->where('contracting_entity_name', $name);
                }
                $this->applyDateStatusCondition($q, 'expiry_date', $status);
            });
        } elseif (! $isAllAgreements) {
            $query->whereHas('businessCollaborationAgreements', fn ($q) => $q->where('contracting_entity_name', $name));
        } else {
            $query->has('businessCollaborationAgreements');
        }
    }

    protected function applyOwnershipFilter(Builder $query, string $ownershipType, ?string $companyUuid): void
    {
        $nombres = $companyUuid ? \App\Utils\OwnCompany::nombresPropios($companyUuid) : [];
        $terminosLike = [];
        foreach ($nombres as $nombre) {
            $terminosLike[] = $nombre;
            if (str_contains($nombre, 'SAS')) {
                $terminosLike[] = str_replace('SAS', 'S.A.S', $nombre);
                $terminosLike[] = str_replace('SAS', 'S.A.S.', $nombre);
                $terminosLike[] = str_replace('SAS', 'S A S', $nombre);
                $raiz = trim((string) preg_replace('/\bSAS\b/i', '', $nombre));
                if (strlen($raiz) >= 4) {
                    $terminosLike[] = $raiz;
                }
            }
        }
        $terminosLike = array_values(array_unique(array_filter($terminosLike)));

        // Evalúa únicamente sobre la tarjeta de operación activa más reciente no cancelada
        $tarjetaMasRecienteEsPropia = function (Builder $subQuery) use ($terminosLike) {
            $subQuery->where(function ($q) {
                $q->whereNull('runt_status')
                    ->orWhere('runt_status', '!=', 'CANCELADA');
            })
            ->whereRaw('operation_cards.uuid = (
                select oc_sub.uuid
                from operation_cards as oc_sub
                where oc_sub.vehicle_uuid = operation_cards.vehicle_uuid
                  and (oc_sub.runt_status != "CANCELADA" or oc_sub.runt_status is null)
                order by oc_sub.expiration_date desc
                limit 1
            )')
            ->where(function ($nq) use ($terminosLike) {
                foreach ($terminosLike as $termino) {
                    $nq->orWhere('affiliated_company', 'like', "%{$termino}%");
                }
            });
        };

        if ($ownershipType === 'propios') {
            $query->where(function ($sub) use ($tarjetaMasRecienteEsPropia, $terminosLike) {
                $sub->where('vehicles.type_of_service', Vehicle::SERVICIO_PARTICULAR);
                if (! empty($terminosLike)) {
                    $sub->orWhereHas('operationCards', $tarjetaMasRecienteEsPropia);
                }
            });
        } elseif ($ownershipType === 'terceros') {
            $query->where('vehicles.type_of_service', '!=', Vehicle::SERVICIO_PARTICULAR);
            if (! empty($terminosLike)) {
                $query->whereDoesntHave('operationCards', $tarjetaMasRecienteEsPropia);
            }
        }
    }

    protected function applyDateStatusCondition(Builder $query, string $column, string $status): void
    {
        $today = Carbon::today()->toDateString();
        switch ($status) {
            case 'vencido':
            case 'vencida':
                $query->whereNotNull($column)->where($column, '<', $today);
                break;
            case 'hoy':
                $query->where($column, '=', $today);
                break;
            case 'por_vencer_5d':
                $fiveDays = Carbon::today()->addDays(5)->toDateString();
                $query->where($column, '>', $today)->where($column, '<=', $fiveDays);
                break;
            case 'mas_15d':
                $fifteenDays = Carbon::today()->addDays(15)->toDateString();
                $query->where($column, '>', $fifteenDays);
                break;
            case 'por_vencer':
                $thirtyDays = Carbon::today()->addDays(30)->toDateString();
                $query->where($column, '>=', $today)->where($column, '<=', $thirtyDays);
                break;
            case 'vigente':
                $query->where($column, '>=', $today);
                break;
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
            $this->applyDateStatusCondition($q, $column, $status);
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
            $card = $vehicle->operationCards
                ->filter(fn ($c) => $c->runt_status !== 'CANCELADA')
                ->sortByDesc('expiration_date')->first();
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

        $branch = $vehicle->relationLoaded('branch') ? $vehicle->branch : null;
        $isOwn = $vehicle->esParticular() || \App\Utils\OwnCompany::esVehiculoPropio($vehicle, $vehicle->company_uuid);

        return [
            'uuid' => $vehicle->uuid,
            'vehicle_license_plate' => $vehicle->vehicle_license_plate,
            'model' => $vehicle->model,
            'vehicle_class' => $vehicle->vehicleClass?->description,
            'body_type' => $vehicle->body_type,
            'type_of_service' => $vehicle->type_of_service,
            'modality_label' => $vehicle->esParticular() ? 'Particular' : 'Público',
            'is_own' => $isOwn,
            'ownership_label' => $isOwn ? 'Propio' : 'Tercero',
            'branch_uuid' => $branch?->uuid,
            'branch_name' => $branch?->name,
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

    /**
     * Genera la estructura cronológica de eventos para el Calendario de Vencimientos y Convenios.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function calendarEvents(array $filtros = []): array
    {
        $filas = $this->allForExport($filtros, 1000);
        $today = Carbon::today();
        $todayStr = $today->toDateString();

        $rawCategory = strtoupper(trim((string) ($filtros['category'] ?? 'ALL')));
        $categoryFilter = in_array($rawCategory, ['', 'ALL', 'TODOS'], true) ? 'ALL' : $rawCategory;

        [$windowStart, $windowEnd] = $this->resolveCalendarWindow(
            isset($filtros['month']) && $filtros['month'] !== '' ? (int) $filtros['month'] : null,
            isset($filtros['year']) && $filtros['year'] !== '' ? (int) $filtros['year'] : null
        );

        $kpis = [
            'soat_alert' => 0,
            'rtm_alert' => 0,
            'operation_card_alert' => 0,
            'polizas_alert' => 0,
            'agreement_upcoming' => 0,
            'agreement_nearest_name' => null,
            'up_to_date_docs' => 0,
            'total_vehicles' => $filas->count(),
        ];

        $categoryCounts = [
            'ALL' => 0,
            'SOAT' => 0,
            'RTM' => 0,
            'OPERATION_CARD' => 0,
            'POLIZAS' => 0,
            'AGREEMENT' => 0,
        ];

        $nearestAgreementDays = null;
        $events = [];
        $definiciones = $this->getDocumentDefinitions();

        foreach ($filas as $row) {
            $esParticular = (bool) ($row['es_particular'] ?? false);

            foreach ($definiciones as $code => $def) {
                if ($esParticular && ! $def['particular_aplica']) {
                    continue;
                }

                $expiryDate = $row[$def['expiry_key']] ?? null;
                if (! $expiryDate) {
                    continue;
                }

                $target = Carbon::parse($expiryDate)->startOfDay();
                $daysRemaining = (int) $today->diffInDays($target, false);
                $status = $this->resolveEventStatus($daysRemaining);
                $isAlert15d = $daysRemaining <= 15;

                // Contabilizar KPIs globales
                if ($code === 'SOAT' && $isAlert15d) {
                    $kpis['soat_alert']++;
                } elseif ($code === 'RTM' && $isAlert15d) {
                    $kpis['rtm_alert']++;
                } elseif ($code === 'OPERATION_CARD' && $isAlert15d) {
                    $kpis['operation_card_alert']++;
                } elseif (in_array($code, ['RCC', 'RCE'], true) && $isAlert15d) {
                    $kpis['polizas_alert']++;
                } elseif ($code === 'AGREEMENT' && $daysRemaining <= 30) {
                    $kpis['agreement_upcoming']++;
                    if ($nearestAgreementDays === null || $daysRemaining < $nearestAgreementDays) {
                        $nearestAgreementDays = $daysRemaining;
                        $kpis['agreement_nearest_name'] = $row['agreement_name'] ?? 'Convenio activo';
                    }
                }

                if ($code !== 'AGREEMENT' && $daysRemaining > 15) {
                    $kpis['up_to_date_docs']++;
                }

                // Filtrar por ventana del mes consultado
                if ($windowStart && $windowEnd) {
                    if ($expiryDate < $windowStart || $expiryDate > $windowEnd) {
                        continue;
                    }
                }

                // Contabilizar category_counts dentro del período del calendario consultado
                $categoryCounts['ALL']++;
                if ($code === 'SOAT') {
                    $categoryCounts['SOAT']++;
                } elseif ($code === 'RTM') {
                    $categoryCounts['RTM']++;
                } elseif ($code === 'OPERATION_CARD') {
                    $categoryCounts['OPERATION_CARD']++;
                } elseif (in_array($code, ['RCC', 'RCE'], true)) {
                    $categoryCounts['POLIZAS']++;
                } elseif ($code === 'AGREEMENT') {
                    $categoryCounts['AGREEMENT']++;
                }

                // Filtrado por categoría seleccionada para los eventos devueltos
                if ($categoryFilter !== 'ALL') {
                    if ($categoryFilter === 'POLIZAS' && ! in_array($code, ['RCC', 'RCE'], true)) {
                        continue;
                    }
                    if ($categoryFilter !== 'POLIZAS' && $code !== $categoryFilter && $def['group'] !== $categoryFilter) {
                        continue;
                    }
                }

                $events[] = $this->buildEventData($row, $code, $def, $expiryDate, $status, $daysRemaining);
            }
        }

        usort($events, static function (array $a, array $b): int {
            $cmpDate = strcmp($a['date'], $b['date']);
            if ($cmpDate !== 0) {
                return $cmpDate;
            }
            $cmpDays = $a['days_remaining'] <=> $b['days_remaining'];
            if ($cmpDays !== 0) {
                return $cmpDays;
            }

            return strcmp((string) $a['vehicle_license_plate'], (string) $b['vehicle_license_plate']);
        });

        return [
            'today' => $todayStr,
            'kpis' => $kpis,
            'category_counts' => $categoryCounts,
            'events' => $events,
        ];
    }

    /**
     * Calcula la ventana de días del mes para el calendario (con 14 días de margen adyacente).
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolveCalendarWindow(?int $month, ?int $year): array
    {
        if (! $month || ! $year) {
            return [null, null];
        }

        $firstDay = Carbon::createFromDate($year, $month, 1)->startOfDay();
        return [
            $firstDay->copy()->subDays(14)->toDateString(),
            $firstDay->copy()->endOfMonth()->addDays(14)->toDateString(),
        ];
    }

    /**
     * Catálogo de definiciones de documentos y convenios para el calendario.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getDocumentDefinitions(): array
    {
        return [
            'SOAT' => [
                'expiry_key' => 'soat_expiry',
                'uuid_key' => 'soat_uuid',
                'label' => 'SOAT',
                'subtitle' => 'Póliza Obligatoria',
                'group' => 'SOAT',
                'particular_aplica' => true,
            ],
            'RTM' => [
                'expiry_key' => 'rtm_expiry',
                'uuid_key' => 'rtm_uuid',
                'label' => 'RTM',
                'subtitle' => 'Revisión Técnico Mecánica',
                'group' => 'RTM',
                'particular_aplica' => true,
            ],
            'OPERATION_CARD' => [
                'expiry_key' => 'operation_card_expiry',
                'uuid_key' => 'operation_card_uuid',
                'label' => 'Tarjeta de Operación',
                'subtitle' => 'Habilitación MinTransporte',
                'group' => 'OPERATION_CARD',
                'particular_aplica' => false,
            ],
            'RCC' => [
                'expiry_key' => 'rcc_expiry',
                'uuid_key' => 'rcc_uuid',
                'label' => 'Póliza RCC',
                'subtitle' => 'Responsabilidad Civil Contractual',
                'group' => 'POLIZAS',
                'particular_aplica' => false,
            ],
            'RCE' => [
                'expiry_key' => 'rce_expiry',
                'uuid_key' => 'rce_uuid',
                'label' => 'Póliza RCE',
                'subtitle' => 'Responsabilidad Civil Extracontractual',
                'group' => 'POLIZAS',
                'particular_aplica' => false,
            ],
            'AGREEMENT' => [
                'expiry_key' => 'agreement_expiry',
                'uuid_key' => null,
                'label' => 'Convenio de Colaboración',
                'subtitle' => 'Convenio Empresarial',
                'group' => 'AGREEMENT',
                'particular_aplica' => true,
            ],
        ];
    }

    /**
     * Determina el estado de alerta según los días restantes para el vencimiento.
     */
    protected function resolveEventStatus(int $daysRemaining): string
    {
        if ($daysRemaining < 0) {
            return 'expired';
        }
        if ($daysRemaining === 0) {
            return 'today';
        }
        if ($daysRemaining <= 5) {
            return 'expiring_5d';
        }
        if ($daysRemaining <= 15) {
            return 'expiring_15d';
        }

        return 'valid';
    }

    /**
     * Estructura el payload individual de un evento del calendario.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    protected function buildEventData(
        array $row,
        string $code,
        array $def,
        string $expiryDate,
        string $status,
        int $daysRemaining
    ): array {
        $subtitle = $def['subtitle'];
        if ($code === 'AGREEMENT' && ! empty($row['agreement_name'])) {
            $subtitle = (string) $row['agreement_name'];
        } elseif ($code === 'OPERATION_CARD' && ! empty($row['operation_card_number'])) {
            $subtitle = 'Habilitación MinTransporte · N.º '.$row['operation_card_number'];
        }

        return [
            'id' => $row['uuid'].'_'.$code.'_'.$expiryDate,
            'date' => $expiryDate,
            'vehicle_uuid' => $row['uuid'],
            'vehicle_license_plate' => $row['vehicle_license_plate'],
            'model' => $row['model'],
            'vehicle_class' => $row['vehicle_class'],
            'modality_label' => $row['modality_label'],
            'is_own' => $row['is_own'],
            'ownership_label' => $row['ownership_label'],
            'affiliate_name' => $row['affiliate_name'],
            'branch_name' => $row['branch_name'],
            'category' => $code,
            'category_group' => $def['group'],
            'category_label' => $def['label'],
            'subtitle' => $subtitle,
            'document_uuid' => $def['uuid_key'] ? ($row[$def['uuid_key']] ?? null) : null,
            'operation_card_number' => $row['operation_card_number'] ?? null,
            'agreement_name' => $row['agreement_name'] ?? null,
            'status' => $status,
            'days_remaining' => $daysRemaining,
        ];
    }
}