<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrige los recorded_at fechados en el futuro: algunos dispositivos (APK)
     * envían la hora en UTC como texto sin zona horaria y el backend la guardaba
     * como hora local (America/Bogota), dejándola ~5 h adelantada. Un punto no
     * puede haberse grabado después de que el servidor lo recibió: cuando
     * recorded_at > created_at se ancla a created_at (hora del servidor, siempre
     * fiable). Idempotente. El código (LocationTrackingService::normalizarRecordedAt)
     * ya impide este desplazamiento para los puntos nuevos.
     */
    public function up(): void
    {
        DB::table('driver_locations')
            ->whereColumn('recorded_at', '>', 'created_at')
            ->update(['recorded_at' => DB::raw('created_at')]);
    }

    /**
     * No se revierte: la hora original (adelantada) del dispositivo no es
     * recuperable ni aporta valor.
     */
    public function down(): void
    {
        //
    }
};
