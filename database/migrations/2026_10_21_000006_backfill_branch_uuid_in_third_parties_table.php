<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Asigna una sede a los terceros que no la tienen.
     *
     * La columna `branch_uuid` nació nullable para no romper los registros
     * existentes; los formularios ya la exigen. Este relleno es idempotente:
     * solo toca filas con `branch_uuid` NULL y toma la sede principal activa
     * de cada empresa (o la primera activa si ninguna está marcada como
     * principal). Si la empresa no tiene sedes activas se deja en NULL y se
     * registra un aviso para asignarla a mano.
     */
    public function up(): void
    {
        $empresas = DB::table('third_parties')
            ->whereNull('branch_uuid')
            ->distinct()
            ->orderBy('company_uuid')
            ->pluck('company_uuid');

        foreach ($empresas as $empresaUuid) {
            $sede = DB::table('branches')
                ->where('company_uuid', $empresaUuid)
                ->where('status', 1)
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->first(['uuid']);

            if (! $sede) {
                Log::warning('Backfill de sucursal en terceros: la empresa no tiene una sede activa.', [
                    'company_uuid' => $empresaUuid,
                ]);

                continue;
            }

            DB::table('third_parties')
                ->where('company_uuid', $empresaUuid)
                ->whereNull('branch_uuid')
                ->update([
                    'branch_uuid' => $sede->uuid,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * No se revierte: antes del relleno no había forma de distinguir qué
     * terceros tenían una sede propia de los que quedaron sin ella.
     */
    public function down(): void
    {
        //
    }
};
