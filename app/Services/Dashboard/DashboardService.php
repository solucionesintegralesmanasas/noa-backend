<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\ControlSheet;
use App\Models\DriverLicense;
use App\Models\Fuec;
use App\Models\Maintenance;
use App\Models\OwnerDriver;
use App\Models\ServiceDeliveryControlSheet;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Services\ContractExtraction\FuecService;
use App\Services\ContractExtraction\ObjectContractService;
use App\Services\Fleet\OwnerDriverService;
use App\Services\Fleet\VehicleService;
use App\Services\Notifications\NotificationsService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @author Darwin Montes
 *
 * @version 2.2.0
 *
 * @created_at 2026-06-30
 *
 * @module Dashboard
 *
 * @resource DashboardMetrics
 */
class DashboardService
{
    public function __construct(
        private ObjectContractService $objectContractService,
        private OwnerDriverService $ownerDriverService,
        private FuecService $fuecService,
        private VehicleService $vehicleService,
        private NotificationsService $notificationsService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSummary(int $days = 30): array
    {
        $companyUuid = $this->getCompanyUuid();
        $cacheKey = 'dashboard_summary_'.($companyUuid ?? 'global').'_'.$days;

        return Cache::remember($cacheKey, 60, function () use ($companyUuid, $days) {
            $currentStartDate = Carbon::now()->subDays($days);
            $previousStartDate = Carbon::now()->subDays($days * 2);

            // Calcular kilómetros recorridos en el período actual y anterior
            $currentKm = $this->getKilometersTraveled($companyUuid, $currentStartDate, Carbon::now());
            $previousKm = $this->getKilometersTraveled($companyUuid, $previousStartDate, $currentStartDate);

            $currentDrivers = $this->ownerDriverService->buildQuery($companyUuid)
                ->where('created_at', '>=', $currentStartDate)->count();
            $previousDrivers = $this->ownerDriverService->buildQuery($companyUuid)
                ->whereBetween('created_at', [$previousStartDate, $currentStartDate])->count();

            $currentFuecs = $this->fuecService->buildQuery($companyUuid)
                ->where('created_at', '>=', $currentStartDate)->count();
            $previousFuecs = $this->fuecService->buildQuery($companyUuid)
                ->whereBetween('created_at', [$previousStartDate, $currentStartDate])->count();

            $currentVehicles = $this->vehicleService->buildQuery($companyUuid)
                ->where('is_active', true)->count();
            $previousVehicles = $this->vehicleService->buildQuery($companyUuid)
                ->where('is_active', true)->where('created_at', '<', $currentStartDate)->count();

            return [
                'stats' => [
                    $this->formatStat('Total Km Recorrido', $currentKm, $previousKm, 'fas fa-road', '#2c7be5', ' km'),
                    $this->formatStat('Conductores Activos', $currentDrivers, $previousDrivers, 'fas fa-id-card', '#00d27a'),
                    $this->formatStat('Nuevos FUEC', $currentFuecs, $previousFuecs, 'fas fa-file-alt', '#f5803e'),
                    $this->formatStat('Vehículos en Servicio', $currentVehicles, $previousVehicles, 'fas fa-bus', '#e63757'),
                ],
            ];
        });
    }

    /**
     * Obtiene el resumen del dashboard específico para el rol CONDUCTOR.
     * Incluye datos del conductor, vehículos usados con kilometraje,
     * métricas clave y accesos directos a los 4 módulos operativos.
     *
     * @return array<string, mixed>
     */
    public function getConductorSummary(int $days = 30): array
    {
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('CONDUCTOR')) {
            return [];
        }

        $userId = $user->uuid ?? $user->id ?? 'unknown';
        $cacheKey = 'dashboard_conductor_summary_'.$userId.'_'.$days;

        return Cache::remember($cacheKey, 60, function () use ($user, $days) {
            $startDate = Carbon::now()->subDays($days)->startOfDay();
            $endDate = Carbon::now()->endOfDay();

            // 1. Resolver el UUID del conductor (ThirdParty) y empresa
            $companyUuid = $user->company_uuid;
            $thirdPartyUuid = $user->third_party_uuid;

            if (! $companyUuid && $user->companies()->first()) {
                $companyUuid = $user->companies()->first()->uuid;
            }

            if (! $thirdPartyUuid && $user->companies()->first()) {
                $thirdPartyUuid = $user->companies()->first()->pivot->third_party_uuid;
            }

            // Obtener datos del conductor
            $conductor = null;
            $driverLicense = null;
            if ($thirdPartyUuid) {
                $conductor = ThirdParty::withoutGlobalScopes()->where('uuid', $thirdPartyUuid)->first();
                $driverLicense = DriverLicense::withoutGlobalScopes()
                    ->where('third_party_uuid', $thirdPartyUuid)
                    ->orderBy('expiration_date', 'desc')
                    ->first();
            }

            // 2. Resolver vehículos vinculados estrictamente al conductor (proyectos, FUECs, historial de asignaciones/cambios)
            $allVehicleUuids = $thirdPartyUuid ? $this->vehicleService->getVehicleUuidsForConductor($thirdPartyUuid) : [];

            // Consultar los vehículos
            $vehicles = ! empty($allVehicleUuids)
                ? Vehicle::withoutGlobalScopes()
                    ->with(['brand', 'vehicleClass'])
                    ->whereIn('uuid', $allVehicleUuids)
                    ->get()
                : collect();

            $vehiclesData = [];
            $totalKmTraveledAllVehicles = 0;

            if ($vehicles->isNotEmpty()) {
                $vehicleUuidsList = $vehicles->pluck('uuid')->all();

                // Batch 1: Max inspection mileage por vehículo
                $maxInspectionMileages = VehicleInspection::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $vehicleUuidsList)
                    ->groupBy('vehicle_uuid')
                    ->select('vehicle_uuid', DB::raw('MAX(mileage) as max_val'))
                    ->pluck('max_val', 'vehicle_uuid');

                // Batch 2: Max maintenance mileage por vehículo
                $maxMaintenanceMileages = Maintenance::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $vehicleUuidsList)
                    ->groupBy('vehicle_uuid')
                    ->select('vehicle_uuid', DB::raw('MAX(mileage) as max_val'))
                    ->pluck('max_val', 'vehicle_uuid');

                // Batch 3: Max control sheet ending kilometer
                $maxControlMileages = DB::table('service_delivery_control_sheet')
                    ->join('service_internal_controls', 'service_delivery_control_sheet.uuid', '=', 'service_internal_controls.service_delivery_control_sheet_uuid')
                    ->whereIn('service_internal_controls.vehicle_uuid', $vehicleUuidsList)
                    ->whereNotNull('service_delivery_control_sheet.ending_kilometer')
                    ->groupBy('service_internal_controls.vehicle_uuid')
                    ->select('service_internal_controls.vehicle_uuid', DB::raw('MAX(CAST(service_delivery_control_sheet.ending_kilometer AS UNSIGNED)) as max_val'))
                    ->pluck('max_val', 'vehicle_uuid');

                // Batch 4: Period inspections agrupadas por vehículo
                $periodInspectionsGrouped = VehicleInspection::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $vehicleUuidsList)
                    ->whereBetween('inspection_date', [$startDate, $endDate])
                    ->orderBy('inspection_date', 'asc')
                    ->get()
                    ->groupBy('vehicle_uuid');

                // Batch 4b: Prev mileage para los que tienen exactamente 1 inspección en el período
                $singleInspectionVehicleUuids = [];
                foreach ($periodInspectionsGrouped as $vUuid => $insps) {
                    if ($insps->count() === 1) {
                        $singleInspectionVehicleUuids[] = $vUuid;
                    }
                }
                $prevMileages = ! empty($singleInspectionVehicleUuids)
                    ? VehicleInspection::withoutGlobalScopes()
                        ->whereIn('vehicle_uuid', $singleInspectionVehicleUuids)
                        ->where('inspection_date', '<', $startDate)
                        ->groupBy('vehicle_uuid')
                        ->select('vehicle_uuid', DB::raw('MAX(mileage) as max_val'))
                        ->pluck('max_val', 'vehicle_uuid')
                    : collect();

                // Batch 5: Control sheets km en el período
                $controlSheetsKmGrouped = DB::table('service_delivery_control_sheet')
                    ->join('service_internal_controls', 'service_delivery_control_sheet.uuid', '=', 'service_internal_controls.service_delivery_control_sheet_uuid')
                    ->whereIn('service_internal_controls.vehicle_uuid', $vehicleUuidsList)
                    ->whereBetween('service_delivery_control_sheet.service_date', [$startDate, $endDate])
                    ->whereNotNull('service_delivery_control_sheet.starting_kilometer')
                    ->whereNotNull('service_delivery_control_sheet.ending_kilometer')
                    ->groupBy('service_internal_controls.vehicle_uuid')
                    ->select('service_internal_controls.vehicle_uuid', DB::raw('SUM(GREATEST(0, CAST(ending_kilometer AS SIGNED) - CAST(starting_kilometer AS SIGNED))) as total_km'))
                    ->pluck('total_km', 'vehicle_uuid');

                // Batch 6: Última inspección de cada vehículo
                $lastInspections = VehicleInspection::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $vehicleUuidsList)
                    ->orderBy('inspection_date', 'desc')
                    ->get()
                    ->unique('vehicle_uuid')
                    ->keyBy('vehicle_uuid');

                // Batch 7: Inspecciones conteo por vehículo para este conductor
                $inspectionsCountGrouped = VehicleInspection::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $vehicleUuidsList)
                    ->when($thirdPartyUuid, fn ($q) => $q->where('driver_uuid', $thirdPartyUuid))
                    ->groupBy('vehicle_uuid')
                    ->select('vehicle_uuid', DB::raw('COUNT(*) as total_count'))
                    ->pluck('total_count', 'vehicle_uuid');

                foreach ($vehicles as $v) {
                    $maxInspectionMileage = (int) ($maxInspectionMileages[$v->uuid] ?? 0);
                    $maxMaintenanceMileage = (int) ($maxMaintenanceMileages[$v->uuid] ?? 0);
                    $maxControlMileage = (int) ($maxControlMileages[$v->uuid] ?? 0);

                    $currentMileage = max($maxInspectionMileage, $maxMaintenanceMileage, $maxControlMileage);

                    $periodInspections = $periodInspectionsGrouped[$v->uuid] ?? collect();
                    $periodKm = 0;
                    if ($periodInspections->count() >= 2) {
                        $periodKm = (int) ($periodInspections->last()->mileage - $periodInspections->first()->mileage);
                    } elseif ($periodInspections->count() === 1) {
                        $prevKm = $prevMileages[$v->uuid] ?? null;
                        if ($prevKm) {
                            $periodKm = (int) ($periodInspections->first()->mileage - (int) $prevKm);
                        }
                    }

                    $controlSheetsKm = (int) ($controlSheetsKmGrouped[$v->uuid] ?? 0);
                    $kmTraveled = max($periodKm, $controlSheetsKm);
                    $totalKmTraveledAllVehicles += $kmTraveled;

                    $lastInspection = $lastInspections[$v->uuid] ?? null;
                    $inspectionsCount = (int) ($inspectionsCountGrouped[$v->uuid] ?? 0);

                    $vehiclesData[] = [
                        'uuid' => $v->uuid,
                        'plate' => $v->vehicle_license_plate,
                        'brand' => $v->brand?->description ?? 'N/A',
                        'line' => $v->line ?? '',
                        'model' => $v->model ?? '',
                        'vehicle_class' => $v->vehicleClass?->description ?? 'VEHÍCULO',
                        'internal_number' => $v->internal_number,
                        'color' => $v->color,
                        'fuel_type' => $v->fuel_type,
                        'type_of_service' => $v->type_of_service,
                        'current_mileage' => $currentMileage,
                        'km_traveled_period' => $kmTraveled,
                        'inspections_count' => $inspectionsCount,
                        'last_inspection_date' => $lastInspection?->inspection_date?->format('Y-m-d') ?? null,
                        'last_inspection_mileage' => $lastInspection?->mileage ?? null,
                        'is_active' => (bool) $v->is_active,
                    ];
                }
            }

            // Conteo de accesos
            $inspectionsTotal = VehicleInspection::withoutGlobalScopes()
                ->when($thirdPartyUuid, fn ($q) => $q->where('driver_uuid', $thirdPartyUuid))
                ->count();

            $fuecsTotal = Fuec::withoutGlobalScopes()
                ->when($thirdPartyUuid, fn ($q) => $q->where(function ($sq) use ($thirdPartyUuid) {
                    $sq->where('main_conductor_uuid', $thirdPartyUuid)
                        ->orWhere('secondary_conductor_uuid', $thirdPartyUuid)
                        ->orWhere('tertiary_conductor_uuid', $thirdPartyUuid);
                }))
                ->count();

            $controlSheetsTotal = empty($allVehicleUuids)
                ? 0
                : ControlSheet::withoutGlobalScopes()
                    ->whereIn('vehicle_uuid', $allVehicleUuids)
                    ->count();

            $serviceDeliverySheetsTotal = DB::table('service_delivery_control_sheet')
                ->join('service_internal_controls', 'service_delivery_control_sheet.uuid', '=', 'service_internal_controls.service_delivery_control_sheet_uuid')
                ->when($thirdPartyUuid, fn ($q) => $q->where('service_internal_controls.third_party_uuid', $thirdPartyUuid))
                ->count();

            // Últimas inspecciones realizadas por el conductor
            $recentInspections = VehicleInspection::withoutGlobalScopes()
                ->with('vehicle')
                ->when($thirdPartyUuid, fn ($q) => $q->where('driver_uuid', $thirdPartyUuid))
                ->orderBy('inspection_date', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($i) => [
                    'uuid' => $i->uuid,
                    'plate' => $i->vehicle?->vehicle_license_plate ?? 'N/A',
                    'date' => $i->inspection_date?->format('Y-m-d'),
                    'mileage' => $i->mileage,
                    'inspector' => $i->inspector_name,
                ]);

            // Últimos FUECs donde participa
            $recentFuecs = Fuec::withoutGlobalScopes()
                ->with(['vehicle', 'contractor'])
                ->when($thirdPartyUuid, fn ($q) => $q->where(function ($sq) use ($thirdPartyUuid) {
                    $sq->where('main_conductor_uuid', $thirdPartyUuid)
                        ->orWhere('secondary_conductor_uuid', $thirdPartyUuid)
                        ->orWhere('tertiary_conductor_uuid', $thirdPartyUuid);
                }))
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($f) => [
                    'uuid' => $f->uuid,
                    'number' => $f->number_fuec ?? 'SIN-REF',
                    'plate' => $f->vehicle?->vehicle_license_plate ?? 'N/A',
                    'contractor' => $f->contractor?->company_name ?? 'N/A',
                    'status' => $f->status,
                ]);

            // Servicio activo (planilla en ruta): iniciada, sin cerrar, del conductor.
            $activeService = null;
            if ($thirdPartyUuid) {
                $enRuta = ServiceDeliveryControlSheet::withoutGlobalScopes()
                    ->with(['project', 'routes', 'internalControl.vehicle', 'parent'])
                    ->where('is_active', true)
                    ->whereNotNull('start_time')
                    ->whereNull('end_time')
                    ->whereHas('internalControl', fn ($q) => $q->where('third_party_uuid', $thirdPartyUuid))
                    ->orderBy('service_date', 'desc')
                    ->first();

                if ($enRuta) {
                    $rutas = $enRuta->routes && $enRuta->routes->isNotEmpty()
                        ? $enRuta->routes->map(fn ($r) => trim(($r->origin ?? '').' - '.($r->destination ?? ''), ' -'))->filter()->values()->all()
                        : [];
                    $activeService = [
                        'uuid' => $enRuta->uuid,
                        'parent_uuid' => $enRuta->parent_uuid,
                        'service_uuid' => $enRuta->parent_uuid ?? $enRuta->uuid,
                        'project_name' => $enRuta->project?->project_name,
                        'service_date' => $enRuta->service_date?->toDateString(),
                        'vehicle_plate' => $enRuta->vehicle_license_plate,
                        'driver_name' => $enRuta->driver_name ?: $enRuta->internalControl?->thirdParty?->company_name,
                        'start_time' => $enRuta->start_time ? Carbon::parse($enRuta->start_time)->format('H:i') : null,
                        'starting_kilometer' => $enRuta->starting_kilometer,
                        'routes_count' => count($rutas),
                        'routes_text' => $rutas ? implode(' · ', $rutas) : ($enRuta->daily_route ?? ''),
                    ];
                }
            }

            return [
                'active_service' => $activeService,
                'conductor' => [
                    'name' => $conductor ? trim($conductor->first_name.' '.$conductor->last_name) : $user->name,
                    'document' => $conductor?->document_number ?? null,
                    'phone' => $conductor?->phone ?? null,
                    'email' => $conductor?->email ?? $user->email,
                    'license' => $driverLicense ? [
                        'license_number' => $driverLicense->number,
                        'category' => $driverLicense->category,
                        'due_date' => $driverLicense->expiration_date instanceof Carbon ? $driverLicense->expiration_date->format('Y-m-d') : (string) $driverLicense->expiration_date,
                        'status' => $driverLicense->status,
                    ] : null,
                ],
                'kpis' => [
                    'total_km' => $totalKmTraveledAllVehicles,
                    'vehicles_count' => count($vehiclesData),
                    'inspections_count' => $inspectionsTotal,
                    'fuecs_count' => $fuecsTotal,
                    'control_sheets_count' => $controlSheetsTotal,
                    'service_delivery_count' => $serviceDeliverySheetsTotal,
                ],
                'quick_access' => [
                    'inspections' => [
                        'title' => 'Inspecciones Vehiculares',
                        'subtitle' => 'Pre-operacional diario',
                        'count' => $inspectionsTotal,
                        'route' => '/inspeccion-vehiculos',
                        'create_route' => '/inspeccion-vehiculos/crear',
                    ],
                    'control_sheets' => [
                        'title' => 'Listado de Planillas de Control',
                        'subtitle' => 'Planillas de control y soporte',
                        'count' => $controlSheetsTotal,
                        'route' => '/planillas-de-control-de-servicios',
                        'create_route' => '/planillas-de-control-de-servicios/crear',
                    ],
                    'fuec' => [
                        'title' => 'Listado de FUEC',
                        'subtitle' => 'Extractos únicos de contrato',
                        'count' => $fuecsTotal,
                        'route' => '/extracto-de-contrato',
                        'create_route' => null,
                    ],
                    'service_delivery' => [
                        'title' => 'Hojas de Control de Servicio',
                        'subtitle' => 'Planilla PCP y recorrido diario',
                        'count' => $serviceDeliverySheetsTotal,
                        'route' => '/planilla-de-control-de-prestacion-servicios',
                        'create_route' => '/planilla-de-control-de-prestacion-servicios/control-de-servicios',
                    ],
                ],
                'vehicles' => $vehiclesData,
                'recent_inspections' => $recentInspections,
                'recent_fuecs' => $recentFuecs,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecentActivity(): array
    {
        try {
            $companyUuid = $this->getCompanyUuid();

            $recentFuecs = $this->fuecService->buildQuery($companyUuid)
                ->with(['vehicle', 'contractor'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($fuec) {
                    return [
                        'id' => $fuec->uuid,
                        'code' => $fuec->number_fuec ?? 'SIN-REF',
                        'client' => $fuec->contractor?->company_name ?? 'N/A',
                        'clientDoc' => 'NIT: '.($fuec->contractor?->document_number ?? 'N/A'),
                        'plate' => $fuec->vehicle?->vehicle_license_plate ?? 'N/A',
                        'statusText' => $fuec->status === 'ACTIVO' ? 'Vigente' : 'Generado',
                        'statusClass' => $fuec->status === 'ACTIVO' ? 'status-success' : 'status-warning',
                    ];
                })
                ->values();

            $maintenancesQuery = Maintenance::query()->with(['vehicle.thirdParty']);

            if ($companyUuid) {
                $maintenancesQuery->where('company_uuid', $companyUuid);
            }

            $recentMaintenances = $maintenancesQuery
                ->orderBy('maintenance_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->take(6)
                ->get()
                ->map(function ($item) {
                    $vehicle = $item->vehicle;
                    $plate = $vehicle?->vehicle_license_plate ?? 'N/A';

                    $driver = $item->mechanic_name;
                    if (! $driver && $vehicle?->thirdParty) {
                        $tp = $vehicle->thirdParty;
                        $driver = trim(($tp->first_name ?? '').' '.($tp->last_name ?? ''));
                        if (empty($driver)) {
                            $driver = $tp->company_name ?? null;
                        }
                    }

                    return [
                        'id' => $item->uuid,
                        'title' => 'Mantenimiento Preventivo',
                        'plate' => $plate,
                        'driver' => $driver ?? 'Conductor no registrado',
                        'desc' => $item->service_description,
                        'mileage' => $item->mileage,
                        'workshop' => $item->workshop_name,
                        'time' => Carbon::parse($item->maintenance_date)->diffForHumans(),
                        'dateFormatted' => Carbon::parse($item->maintenance_date)->format('d/m/Y'),
                        'color' => '#00d27a',
                    ];
                })
                ->values();

            return [
                'contracts' => $recentFuecs,
                'activities' => $recentMaintenances,
            ];
        } catch (\Throwable $e) {
            Logger::error('DashboardService@getRecentActivity: '.$e->getMessage(), $e);

            return [
                'contracts' => [],
                'activities' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlerts(): array
    {
        $companyUuid = $this->getCompanyUuid();
        $cacheKey = 'dashboard_alerts_'.($companyUuid ?? 'global');

        return Cache::remember($cacheKey, 180, function () use ($companyUuid) {
            $all = $this->notificationsService->getAllNotifications($companyUuid);

            $alerts = [];
            $idCounter = 1;

            $addAlert = function (string $title, string $tag, string $desc, string $severity) use (&$alerts, &$idCounter) {
                $alerts[] = [
                    'id' => $idCounter++,
                    'title' => $title,
                    'tag' => $tag,
                    'desc' => $desc,
                    'severity' => $severity,
                ];
            };

            // 1. Documentos de Vehículos
            if (isset($all['vehicle_documents'])) {
                $docs = $all['vehicle_documents'];
                foreach ($docs['expired'] ?? [] as $item) {
                    $addAlert(
                        ($item['document_type'] ?? 'Documento').' Vencido',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Documento vencido.',
                        'high'
                    );
                }
                foreach ($docs['expiring_soon'] ?? [] as $item) {
                    $addAlert(
                        ($item['document_type'] ?? 'Documento').' por vencer',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Documento por vencer.',
                        'medium'
                    );
                }
                foreach ($docs['missing'] ?? [] as $item) {
                    $addAlert(
                        'Documento Faltante: '.($item['document_type'] ?? 'N/A'),
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Falta registrar documento.',
                        'high'
                    );
                }
            }

            // 2. Tarjetas de Operación
            if (isset($all['operation_cards'])) {
                $cards = $all['operation_cards'];
                foreach ($cards['expired'] ?? [] as $item) {
                    $addAlert(
                        'Tarjeta de Operación Vencida',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Tarjeta de operación vencida.',
                        'high'
                    );
                }
                foreach ($cards['expiring_soon'] ?? [] as $item) {
                    $addAlert(
                        'Tarjeta de Operación por vencer',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Tarjeta de operación por vencer.',
                        'medium'
                    );
                }
                foreach ($cards['missing'] ?? [] as $item) {
                    $addAlert(
                        'Tarjeta de Operación Faltante',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Falta registrar tarjeta de operación.',
                        'high'
                    );
                }
            }

            // 3. Licencias de Conducción
            if (isset($all['driver_licenses'])) {
                $licenses = $all['driver_licenses'];
                foreach ($licenses['expired'] ?? [] as $item) {
                    $addAlert(
                        'Licencia de Conducción Vencida',
                        $item['driver_name'] ?? 'Conductor',
                        $item['message'] ?? 'Licencia de conducción vencida.',
                        'high'
                    );
                }
                foreach ($licenses['expiring_soon'] ?? [] as $item) {
                    $addAlert(
                        'Licencia de Conducción por vencer',
                        $item['driver_name'] ?? 'Conductor',
                        $item['message'] ?? 'Licencia de conducción por vencer.',
                        'medium'
                    );
                }
            }

            // 4. Primera RTM
            if (isset($all['first_rtm'])) {
                $rtm = $all['first_rtm'];
                foreach ($rtm['expired'] ?? [] as $item) {
                    $addAlert(
                        'Primera RTM Vencida',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Primera RTM vencida.',
                        'high'
                    );
                }
                foreach ($rtm['expiring_soon'] ?? [] as $item) {
                    $addAlert(
                        'Primera RTM por realizar',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Primera RTM por realizar.',
                        'medium'
                    );
                }
            }

            // 5. Convenios
            if (isset($all['agreements'])) {
                $agreements = $all['agreements'];
                foreach ($agreements['expired'] ?? [] as $item) {
                    $addAlert(
                        'Convenio Vencido',
                        $item['license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Convenio vencido.',
                        'high'
                    );
                }
                foreach ($agreements['expiring_soon'] ?? [] as $item) {
                    $addAlert(
                        'Convenio por vencer',
                        $item['license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Convenio por vencer.',
                        'medium'
                    );
                }
            }

            // 6. Cobros de Afiliación
            if (isset($all['affiliate_charges'])) {
                $charges = $all['affiliate_charges'];
                foreach ($charges['expired'] ?? [] as $item) {
                    $addAlert(
                        'Cobro de Administración Vencido',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Cobro vencido.',
                        'high'
                    );
                }
                foreach ($charges['expiring_soon'] ?? [] as $item) {
                    $addAlert(
                        'Cobro de Administración Próximo',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Cobro próximo.',
                        'medium'
                    );
                }
            }

            // 7. Mantenimiento Preventivo
            if (isset($all['preventative_maintenance'])) {
                foreach ($all['preventative_maintenance'] as $item) {
                    $addAlert(
                        $item['title'] ?? 'Mantenimiento Preventivo',
                        $item['vehicle_license_plate'] ?? 'N/A',
                        $item['message'] ?? 'Mantenimiento preventivo.',
                        ($item['status'] ?? '') === 'VENCIDO' ? 'high' : 'medium'
                    );
                }
            }

            // 8. Seguridad Social
            if (isset($all['social_security']['mora'])) {
                foreach ($all['social_security']['mora'] as $item) {
                    $addAlert(
                        $item['title'] ?? 'Seguridad Social en Mora',
                        $item['third_party_name'] ?? 'Tercero',
                        $item['message'] ?? 'Seguridad social en mora.',
                        'high'
                    );
                }
            }

            return $alerts;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStat(string $label, int $current, int $previous, string $icon, string $color, ?string $valueSuffix = null): array
    {
        $trend = $previous > 0 ? (int) round((($current - $previous) / $previous) * 100) : ($current > 0 ? 100 : 0);
        $formattedValue = number_format($current, 0, ',', '.');
        if ($valueSuffix) {
            $formattedValue .= $valueSuffix;
        }

        return [
            'label' => $label,
            'value' => $formattedValue,
            'current' => $current,
            'previous' => $previous,
            'icon' => $icon,
            'color' => $color,
            'trend' => $trend,
        ];
    }

    /**
     * Calcula los kilómetros recorridos por la flota en un rango de fechas.
     */
    private function getKilometersTraveled(?string $companyUuid, Carbon $startDate, Carbon $endDate): int
    {
        $vehicleUuids = $this->vehicleService->buildQuery($companyUuid)->pluck('uuid');

        if ($vehicleUuids->isEmpty()) {
            return 0;
        }

        $inspections = DB::table('vehicle_inspections')
            ->whereIn('vehicle_uuid', $vehicleUuids)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('vehicle_uuid');

        $baselineMileages = DB::table('vehicle_inspections')
            ->whereIn('vehicle_uuid', $vehicleUuids)
            ->where('created_at', '<', $startDate)
            ->groupBy('vehicle_uuid')
            ->select('vehicle_uuid', DB::raw('MAX(mileage) as max_mileage'))
            ->pluck('max_mileage', 'vehicle_uuid');

        $totalKm = 0;

        foreach ($inspections as $vehicleUuid => $vehicleInspections) {
            $maxMileage = $vehicleInspections->max('mileage') ?? 0;

            $baselineMileage = $baselineMileages[$vehicleUuid] ?? null;

            if (is_null($baselineMileage)) {
                $baselineMileage = $vehicleInspections->min('mileage') ?? 0;
            }

            $diff = $maxMileage - $baselineMileage;
            if ($diff > 0) {
                $totalKm += $diff;
            }
        }

        return $totalKm;
    }

    /**
     * Retorna el UUID de la empresa actual, aislando la data (Multi-tenant).
     * Si es SUPERADMIN retorna null para ver toda la flota global.
     */
    private function getCompanyUuid(): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        // Si es superadmin tiene acceso a los datos de todas las empresas.
        if (method_exists($user, 'hasRole') && $user->hasRole('SUPERADMIN')) {
            return null;
        }

        return $user->company_uuid ?? null;
    }
}
