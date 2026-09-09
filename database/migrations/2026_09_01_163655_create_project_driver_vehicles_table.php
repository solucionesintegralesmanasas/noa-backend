<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vinculación conductor → vehículo dentro de un proyecto (relación 1:1).
 *
 * Reemplaza los pivotes independientes project_third_parties y project_vehicles:
 * cada proyecto asigna a cada conductor exactamente UN vehículo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_driver_vehicles', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique('uk_pdv_uuid')->comment('UUID v7 único de la asignación');
            $table->uuid('project_uuid')->comment('UUID del proyecto');
            $table->uuid('third_party_uuid')->comment('UUID del conductor (tercero) asignado al proyecto');
            $table->uuid('vehicle_uuid')->comment('UUID del vehículo asignado a ese conductor en el proyecto');
            $table->timestamps();

            $table->unique(['project_uuid', 'third_party_uuid'], 'uk_pdv_driver');
            $table->unique(['project_uuid', 'vehicle_uuid'], 'uk_pdv_vehicle');

            $table->index('third_party_uuid', 'idx_pdv_third_party');
            $table->index('vehicle_uuid', 'idx_pdv_vehicle');

            $table->foreign('project_uuid', 'fk_pdv_project')
                ->references('uuid')->on('projects')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('third_party_uuid', 'fk_pdv_third_party')
                ->references('uuid')->on('third_parties')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('vehicle_uuid', 'fk_pdv_vehicle')
                ->references('uuid')->on('vehicles')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        DB::statement("ALTER TABLE `project_driver_vehicles` comment 'Asignación conductor-vehículo por proyecto (relación 1:1)'");

        $this->seedFromLegacyPivotes();

        Schema::dropIfExists('project_vehicles');
        Schema::dropIfExists('project_third_parties');
    }

    /**
     * Migra los datos existentes: combina 1:1 (por orden de creación) los
     * conductores y vehículos que cada proyecto ya tenía en los pivotes viejos.
     */
    private function seedFromLegacyPivotes(): void
    {
        if (! Schema::hasTable('project_third_parties') || ! Schema::hasTable('project_vehicles')) {
            return;
        }

        $legacyThirdParties = DB::table('project_third_parties')->orderBy('id')->get();
        $legacyVehicles = DB::table('project_vehicles')->orderBy('id')->get();

        $thirdPartiesByProject = $legacyThirdParties->groupBy('project_uuid');
        $vehiclesByProject = $legacyVehicles->groupBy('project_uuid');

        foreach ($thirdPartiesByProject as $projectUuid => $rows) {
            $vehicles = $vehiclesByProject->get($projectUuid) ?? collect();

            foreach ($rows as $index => $row) {
                $vehicle = $vehicles->get($index);

                if (! $vehicle) {
                    continue;
                }

                DB::table('project_driver_vehicles')->insert([
                    'uuid' => Str::uuid()->toString(),
                    'project_uuid' => $projectUuid,
                    'third_party_uuid' => $row->third_party_uuid,
                    'vehicle_uuid' => $vehicle->vehicle_uuid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::create('project_third_parties', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique('uk_project_third_parties_uuid')->comment('UUID v7 único de la asignación');
            $table->uuid('project_uuid')->comment('UUID del proyecto');
            $table->uuid('third_party_uuid')->comment('UUID del conductor (tercero) asignado al proyecto');
            $table->timestamps();

            $table->unique(['project_uuid', 'third_party_uuid'], 'uk_project_third_party');

            $table->foreign('project_uuid', 'fk_project_third_parties_project')
                ->references('uuid')->on('projects')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('third_party_uuid', 'fk_project_third_parties_third_party')
                ->references('uuid')->on('third_parties')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        DB::statement("ALTER TABLE `project_third_parties` comment 'Asignación de conductores a proyectos'");

        Schema::create('project_vehicles', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique('uk_project_vehicles_uuid')->comment('UUID v7 único de la asignación');
            $table->uuid('project_uuid')->comment('UUID del proyecto');
            $table->uuid('vehicle_uuid')->comment('UUID del vehículo asignado al proyecto');
            $table->timestamps();

            $table->unique(['project_uuid', 'vehicle_uuid'], 'uk_project_vehicle');

            $table->foreign('project_uuid', 'fk_project_vehicles_project')
                ->references('uuid')->on('projects')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('vehicle_uuid', 'fk_project_vehicles_vehicle')
                ->references('uuid')->on('vehicles')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        DB::statement("ALTER TABLE `project_vehicles` comment 'Asignación de vehículos a proyectos'");

        $assignments = DB::table('project_driver_vehicles')->orderBy('id')->get();

        $now = now();

        foreach ($assignments as $assignment) {
            DB::table('project_third_parties')->insert([
                'uuid' => Str::uuid()->toString(),
                'project_uuid' => $assignment->project_uuid,
                'third_party_uuid' => $assignment->third_party_uuid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('project_vehicles')->insert([
                'uuid' => Str::uuid()->toString(),
                'project_uuid' => $assignment->project_uuid,
                'vehicle_uuid' => $assignment->vehicle_uuid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::dropIfExists('project_driver_vehicles');
    }
};