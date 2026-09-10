<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite conservar el historial de asignaciones de vehículos a conductores en proyectos.
 * Al cambiar de vehículo, la asignación anterior queda registrada con is_active = false
 * sin provocar error de clave duplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM project_driver_vehicles'))->pluck('Key_name')->unique();

        if (! $indexes->contains('idx_pdv_project')) {
            DB::statement('CREATE INDEX idx_pdv_project ON project_driver_vehicles (project_uuid)');
        }

        if ($indexes->contains('uk_pdv_driver')) {
            DB::statement('ALTER TABLE project_driver_vehicles DROP INDEX uk_pdv_driver');
        }

        if ($indexes->contains('uk_pdv_vehicle')) {
            DB::statement('ALTER TABLE project_driver_vehicles DROP INDEX uk_pdv_vehicle');
        }

        $updatedIndexes = collect(DB::select('SHOW INDEX FROM project_driver_vehicles'))->pluck('Key_name')->unique();

        if (! $updatedIndexes->contains('idx_pdv_project_driver')) {
            DB::statement('CREATE INDEX idx_pdv_project_driver ON project_driver_vehicles (project_uuid, third_party_uuid)');
        }

        if (! $updatedIndexes->contains('idx_pdv_project_vehicle')) {
            DB::statement('CREATE INDEX idx_pdv_project_vehicle ON project_driver_vehicles (project_uuid, vehicle_uuid)');
        }
    }

    public function down(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM project_driver_vehicles'))->pluck('Key_name')->unique();

        if ($indexes->contains('idx_pdv_project_driver')) {
            DB::statement('ALTER TABLE project_driver_vehicles DROP INDEX idx_pdv_project_driver');
        }

        if ($indexes->contains('idx_pdv_project_vehicle')) {
            DB::statement('ALTER TABLE project_driver_vehicles DROP INDEX idx_pdv_project_vehicle');
        }
    }
};
