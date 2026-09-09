<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;

/**
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created_at 2026-06-30
 *
 * @module Fleet
 *
 * @resource Vehicle
 */
class PreventiveMaintenanceService
{
    /**
     * Catálogo estático de planes de mantenimiento preventivo.
     * Estos son los intervalos definidos por el usuario.
     */
    private const MAINTENANCE_PLANS = [
        [
            'id' => '5k-10k',
            'name' => 'Mantenimiento 5,000 km - 10,000 km',
            'description' => 'Cambio de aceite de motor y filtro de aceite. Rotación de llantas. Inspección de niveles de líquidos (frenos, refrigerante). Frecuencia: Cada 3 a 6 meses.',
            'interval_km' => 10000,
            'category' => 'rutina',
        ],
        [
            'id' => '15k-30k',
            'name' => 'Mantenimiento 15,000 km - 30,000 km',
            'description' => 'Cambio de filtro de aire del motor y filtro de cabina. Cambio de filtro de combustible. Limpieza y ajuste de frenos. Frecuencia: Cada 12 a 24 meses.',
            'interval_km' => 30000,
            'category' => 'intermedio',
        ],
        [
            'id' => '30k-60k',
            'name' => 'Mantenimiento 30,000 km - 60,000 km',
            'description' => 'Cambio de líquido de frenos y refrigerante. Inspección de bujías (cambio si son convencionales). Cambio de fluido de transmisión manual. Frecuencia: Según uso.',
            'interval_km' => 60000,
            'category' => 'profundo',
        ],
        [
            'id' => '60k-100k',
            'name' => 'Mantenimiento 60,000 km - 100,000 km',
            'description' => 'Reemplazo de la correa (banda) de distribución. Inspección y posible cambio de amortiguadores y batería. Revisión de pastillas de freno y discos. Frecuencia: Alta intensidad.',
            'interval_km' => 100000,
            'category' => 'critico',
        ],
    ];

    public function __construct(
        private readonly VehicleService $vehicleService,
        private readonly VehicleInspectionService $vehicleInspectionService,
        private readonly MaintenanceService $maintenanceService,
        private readonly ServiceDeliveryControlSheetService $serviceDeliveryControlSheetService
    ) {}

    /**
     * Calcula el pronóstico de mantenimientos para un vehículo específico.
     *
     * @return array<string, mixed>
     */
    public function calculateForecast(string $vehicleUuid): array
    {
        $vehicle = $this->vehicleService->findByUuid($vehicleUuid);
        if (! $vehicle) {
            throw new \RuntimeException('Vehículo no encontrado.');
        }

        // 1. Obtener el kilometraje actual integrando todas las fuentes
        $mileage = $this->resolveCurrentMileage($vehicleUuid);

        // 2. Último mantenimiento preventivo real del vehículo (una sola consulta)
        $lastPreventive = $this->maintenanceService->query()
            ->where('vehicle_uuid', $vehicleUuid)
            ->where('maintenance_type', 'PREVENTIVA')
            ->orderBy('mileage', 'desc')
            ->first();

        $lastPreventiveMileage = $lastPreventive ? (int) $lastPreventive->mileage : 0;
        $lastPreventiveDate = $lastPreventive && $lastPreventive->maintenance_date
            ? $lastPreventive->maintenance_date->format('Y-m-d')
            : null;

        $forecasts = [];

        foreach (self::MAINTENANCE_PLANS as $plan) {
            // Base = el último hito cerrado del intervalo según el mantenimiento preventivo real.
            // Ej: último preventivo en 32.000 km e intervalo 30.000 → base 30.000, próximo 60.000.
            $baseMileage = intdiv($lastPreventiveMileage, $plan['interval_km']) * $plan['interval_km'];

            $nextDueMileage = $baseMileage + $plan['interval_km'];

            $kmRemaining = $nextDueMileage - $mileage['current'];

            $status = 'OPTIMO';
            if ($kmRemaining <= 0) {
                $status = 'VENCIDO';
            } elseif ($kmRemaining <= 1500) {
                $status = 'PROXIMO';
            }

            $progressPercentage = 0;
            if ($kmRemaining <= 0) {
                $progressPercentage = 100;
            } else {
                $kmPassed = $mileage['current'] - $baseMileage;
                if ($kmPassed > 0) {
                    $progressPercentage = min(100, (int) round(($kmPassed / $plan['interval_km']) * 100));
                }
            }

            $forecasts[] = [
                'plan_id' => $plan['id'],
                'plan_name' => $plan['name'],
                'description' => $plan['description'],
                'interval_km' => $plan['interval_km'],
                'last_maintenance_mileage' => $baseMileage,
                'last_maintenance_date' => $lastPreventiveDate,
                'current_mileage' => $mileage['current'],
                'next_due_mileage' => $nextDueMileage,
                'km_remaining' => $kmRemaining,
                'status' => $status,
                'progress_percentage' => $progressPercentage,
            ];
        }

        // Ordenar por cercanía al vencimiento (menor remaining km primero)
        usort($forecasts, fn ($a, $b) => $a['km_remaining'] <=> $b['km_remaining']);

        return [
            'vehicle_uuid' => $vehicleUuid,
            'plate' => $vehicle->vehicle_license_plate,
            'current_mileage' => $mileage['current'],
            'mileage_sources' => $mileage['sources'],
            'last_preventive_mileage' => $lastPreventiveMileage,
            'last_preventive_date' => $lastPreventiveDate,
            'forecasts' => $forecasts,
        ];
    }

    /**
     * Resuelve el kilometraje actual del vehículo a partir de todas las fuentes
     * de lecturas disponibles en el sistema:
     * - Inspecciones de vehículo (vehicle_inspections.mileage)
     * - Mantenimientos (maintenance.mileage)
     * - Planillas de control de prestación de servicios
     *   (service_delivery_control_sheet.ending_kilometer vía service_internal_controls.vehicle_uuid)
     *
     * @return array{current: int, sources: array<int, array<string, mixed>>}
     */
    private function resolveCurrentMileage(string $vehicleUuid): array
    {
        $sources = [];

        // 1. Inspección de vehículo con mayor kilometraje
        $inspection = $this->vehicleInspectionService->query()
            ->where('vehicle_uuid', $vehicleUuid)
            ->whereNotNull('mileage')
            ->orderByRaw('mileage DESC')
            ->first();

        if ($inspection) {
            $sources[] = [
                'tipo' => 'INSPECCION',
                'etiqueta' => 'Inspección de vehículo',
                'valor' => (int) $inspection->mileage,
                'fecha' => $inspection->inspection_date ? $inspection->inspection_date->format('Y-m-d') : null,
            ];
        }

        // 2. Mantenimiento con mayor kilometraje
        $maintenance = $this->maintenanceService->query()
            ->where('vehicle_uuid', $vehicleUuid)
            ->whereNotNull('mileage')
            ->orderByRaw('mileage DESC')
            ->first();

        if ($maintenance) {
            $sources[] = [
                'tipo' => 'MANTENIMIENTO',
                'etiqueta' => 'Mantenimiento (km al servicio)',
                'valor' => (int) $maintenance->mileage,
                'fecha' => $maintenance->maintenance_date ? $maintenance->maintenance_date->format('Y-m-d') : null,
            ];
        }

        // 3. Planilla de control de prestación de servicios con mayor km final del vehículo.
        // Los valores se almacenan como string(10), por lo que se castean a entero para ordenar.
        $controlSheet = $this->serviceDeliveryControlSheetService->query()
            ->whereHas('internalControl', function ($q) use ($vehicleUuid) {
                $q->where('vehicle_uuid', $vehicleUuid);
            })
            ->whereNotNull('ending_kilometer')
            ->orderByRaw('CAST(ending_kilometer AS UNSIGNED) DESC')
            ->first();

        if ($controlSheet) {
            $sources[] = [
                'tipo' => 'PLANILLA',
                'etiqueta' => 'Planilla de control de servicio',
                'valor' => (int) $controlSheet->ending_kilometer,
                'fecha' => $controlSheet->service_date ? $controlSheet->service_date->format('Y-m-d') : null,
            ];
        }

        $current = max(array_map(fn ($s) => $s['valor'], $sources) ?: [0]);

        // Marcar la fuente que define el kilometraje vigente
        foreach ($sources as &$source) {
            $source['is_current'] = $source['valor'] === $current;
        }
        unset($source);

        return [
            'current' => (int) $current,
            'sources' => array_values($sources),
        ];
    }
}
