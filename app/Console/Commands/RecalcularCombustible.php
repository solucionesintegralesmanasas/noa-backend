<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FuelLog;
use App\Services\Fleet\FuelLogService;
use Illuminate\Console\Command;

/**
 * Recalcula la cadena de combustible (saldos, km/gal, rendimiento aplicado y
 * alcance) de los vehículos con registros. Útil para poblar `average_performance`
 * y `performance_source` en registros creados antes de esta funcionalidad.
 */
class RecalcularCombustible extends Command
{
    protected $signature = 'fuel:recalcular {--vehicle= : UUID de un vehículo específico}';

    protected $description = 'Recalcula rendimiento y alcance de los registros de combustible';

    public function handle(FuelLogService $service): int
    {
        $query = FuelLog::withoutGlobalScopes()->select('vehicle_uuid')->distinct();

        if ($uuid = $this->option('vehicle')) {
            $query->where('vehicle_uuid', $uuid);
        }

        $vehiculos = $query->pluck('vehicle_uuid');

        foreach ($vehiculos as $vehicleUuid) {
            $service->recalcularFlujo((string) $vehicleUuid);
        }

        $this->info("Recalculados {$vehiculos->count()} vehículo(s) con registros de combustible.");

        return self::SUCCESS;
    }
}
