<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Maintenance;
use App\Services\ContractExtraction\FuecService;
use App\Services\ContractExtraction\ObjectContractService;
use App\Services\Fleet\OwnerDriverService;
use App\Services\Fleet\VehicleService;
use App\Services\Notifications\NotificationsService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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
        $currentStartDate = Carbon::now()->subDays($days);
        $previousStartDate = Carbon::now()->subDays($days * 2);

        $companyUuid = $this->getCompanyUuid();

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

        $totalKm = 0;

        foreach ($inspections as $vehicleUuid => $vehicleInspections) {
            $maxMileage = $vehicleInspections->max('mileage') ?? 0;

            $baselineMileage = DB::table('vehicle_inspections')
                ->where('vehicle_uuid', $vehicleUuid)
                ->where('created_at', '<', $startDate)
                ->max('mileage');

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
