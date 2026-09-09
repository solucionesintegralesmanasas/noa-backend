<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite "retirar" una asignación conductor-vehículo del proyecto sin borrarla:
 * al marcarla como inactiva se conserva el historial y deja de contar en el proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_driver_vehicles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('vehicle_uuid');
        });

        DB::statement("ALTER TABLE `project_driver_vehicles` MODIFY `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Indica si la asignación conductor-vehículo sigue activa en el proyecto'");
    }

    public function down(): void
    {
        Schema::table('project_driver_vehicles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};