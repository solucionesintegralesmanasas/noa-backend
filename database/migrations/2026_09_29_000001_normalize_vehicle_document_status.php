<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza el estado histórico de documentos vehiculares al vocabulario
     * vigente: SI → VIGENTE y NO → NO VIGENTE.
     *
     * El formulario ya solo ofrece VIGENTE / NO VIGENTE y el resto del sistema
     * (listados, FUEC, notificaciones) trabaja con esos valores. Sin reversa:
     * VIGENTE también existe por derecho propio (SOAT, pólizas) y no se puede
     * distinguir del que viene de SI.
     */
    public function up(): void
    {
        DB::table('vehicle_documents')->where('status', 'SI')->update(['status' => 'VIGENTE']);
        DB::table('vehicle_documents')->where('status', 'NO')->update(['status' => 'NO VIGENTE']);
    }

    /**
     * Sin reversa: es una normalización de datos, no un cambio de esquema.
     */
    public function down(): void
    {
        // Sin operación.
    }
};
