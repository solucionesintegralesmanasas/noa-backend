<?php

namespace App\Console\Commands;

use App\Models\ProjectDriverVehicle;
use App\Models\ServiceDeliveryControlSheet;
use Illuminate\Console\Command;

class RestoreProjectSheetRetiradas extends Command
{
    protected $signature = 'project:sheets:restore-retiradas {--project=}';
    protected $description = 'Restaura hojas marcadas como retiradas cuando el conductor sigue activo en el proyecto con otro vehículo';

    public function handle(): int
    {
        $projectUuid = $this->option('project');
        
        if (!$projectUuid) {
            $this->error('Debe especificar --project=<uuid>');
            return Command::FAILURE;
        }

        $asignaciones = ProjectDriverVehicle::query()
            ->where('project_uuid', $projectUuid)
            ->get(['vehicle_uuid', 'third_party_uuid', 'is_active']);

        $activos = $asignaciones->where('is_active', true);
        $conductoresActivos = $activos->pluck('third_party_uuid')->unique()->values();
        $activosVehiculos = $activos->pluck('vehicle_uuid')->unique()->values();

        $vehiculosInactivos = $asignaciones
            ->where('is_active', false)
            ->pluck('vehicle_uuid');

        // Restaurar hojas de vehículos retirados cuyo conductor aún está activo en el proyecto con otro coche
        $vehiculosARestaurar = $asignaciones
            ->where('is_active', false)
            ->filter(fn (ProjectDriverVehicle $a) => ! $activosVehiculos->contains($a->vehicle_uuid))
            ->groupBy('vehicle_uuid')
            ->filter(fn ($filas) => $filas->pluck('third_party_uuid')->intersect($conductoresActivos)->isNotEmpty())
            ->keys()
            ->values();

        $restauradas = 0;
        if ($vehiculosARestaurar->isNotEmpty()) {
            $restauradas = ServiceDeliveryControlSheet::where('project_uuid', $projectUuid)
                ->whereHas('internalControl', fn ($q) => $q->whereIn('vehicle_uuid', $vehiculosARestaurar))
                ->where('retirada_de_proyecto', true)
                ->update(['retirada_de_proyecto' => false]);
        }

        $this->info("Comando ejecutado. Hojas restauradas: {$restauradas}.");
        return Command::SUCCESS;
    }
}
