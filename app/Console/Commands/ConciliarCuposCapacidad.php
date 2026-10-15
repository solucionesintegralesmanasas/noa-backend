<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CapacityMovement;
use App\Models\ConveyorCapacity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcilia el cupo disponible (`conveyor_capacity.current_capacity`) contra el
 * libro mayor de movimientos de cupos (capacity_movements).
 *
 * Saldo esperado = capacidad autorizada + Σ(signo × cantidad) de los movimientos
 * aplicados. Sin `--apply` solo informa las diferencias (dry-run).
 */
class ConciliarCuposCapacidad extends Command
{
    protected $signature = 'capacity:reconciliar {--apply : Aplica los ajustes de cupo encontrados}';

    protected $description = 'Concilia conveyor_capacity.current_capacity contra el libro mayor de movimientos de cupos';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $capacidades = ConveyorCapacity::withoutGlobalScopes()->orderBy('id')->get();
        $diferencias = 0;

        foreach ($capacidades as $capacidad) {
            $neto = CapacityMovement::withoutGlobalScopes()
                ->where('conveyor_capacity_uuid', $capacidad->uuid)
                ->where('applied', true)
                ->get()
                ->sum(fn (CapacityMovement $m) => CapacityMovement::signo($m->movement_type) * $m->quantity);

            $esperado = max(0, min((int) $capacidad->authorized_capacity, (int) $capacidad->authorized_capacity + $neto));
            $actual = (int) $capacidad->current_capacity;

            if ($esperado === $actual) {
                continue;
            }

            $diferencias++;
            $this->warn(sprintf(
                'Capacidad %s (%s): actual %d, esperado %d.',
                $capacidad->uuid,
                $capacidad->vehicle_type,
                $actual,
                $esperado
            ));

            if ($aplicar) {
                DB::transaction(function () use ($capacidad, $esperado) {
                    ConveyorCapacity::withoutGlobalScopes()
                        ->whereKey($capacidad->getKey())
                        ->update(['current_capacity' => $esperado, 'updated_at' => now()]);
                });
            }
        }

        if ($diferencias === 0) {
            $this->info('Sin diferencias: el cupo coincide con el libro mayor de movimientos.');

            return self::SUCCESS;
        }

        $this->info($aplicar
            ? "Se ajustaron {$diferencias} capacidad(es)."
            : "Se encontraron {$diferencias} diferencia(s). Ejecute con --apply para ajustarlas.");

        return self::SUCCESS;
    }
}
