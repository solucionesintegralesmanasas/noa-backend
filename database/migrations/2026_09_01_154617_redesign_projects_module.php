<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rediseña el módulo de proyectos:
 *  - Elimina la tabla project_assignments (asignación directa conductor-proyecto).
 *  - Crea la tabla maestra projects.
 *  - Crea los pivotes project_third_parties (proyecto ↔ conductores) y
 *    project_vehicles (proyecto ↔ vehículos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_assignments');

        Schema::create('projects', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique('uk_projects_uuid')->comment('UUID v7 único del proyecto');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->string('project_name', 255)->comment('Nombre del proyecto');
            $table->date('start_date')->comment('Fecha de inicio');
            $table->date('completion_date')->comment('Fecha de finalización');
            $table->decimal('project_value', 18, 2)->default(0)->comment('Valor del proyecto');
            $table->string('purchase_order', 255)->nullable()->comment('Orden de compra');
            $table->timestamps();

            $table->index('company_uuid', 'idx_projects_company_uuid');

            $table->foreign('company_uuid', 'fk_projects_company')
                ->references('uuid')->on('companies')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        DB::statement("ALTER TABLE `projects` comment 'Proyectos maestros (pueden tener varios conductores y vehículos)'");

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
    }

    public function down(): void
    {
        Schema::dropIfExists('project_vehicles');
        Schema::dropIfExists('project_third_parties');
        Schema::dropIfExists('projects');

        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique('uk_project_assignments_uuid')->comment('UUID v7 único de la asignación de proyecto');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->uuid('third_parties_uuid')->comment('UUID del tercero (conductor) al que se asigna el proyecto');
            $table->string('project_name', 255)->comment('Nombre del proyecto');
            $table->date('start_date')->comment('Fecha de inicio');
            $table->date('completion_date')->comment('Fecha de finalización');
            $table->decimal('project_value', 10, 2)->default(0)->comment('Valor del proyecto');
            $table->string('purchase_order', 255)->comment('Orden de compra');
            $table->timestamps();

            $table->index('third_parties_uuid', 'idx_project_assignments_third_parties_uuid');
            $table->index('company_uuid', 'idx_project_assignments_company_uuid');

            $table->foreign('third_parties_uuid', 'fk_project_assignments_third_parties')
                ->references('uuid')->on('third_parties')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('company_uuid', 'fk_project_assignments_company')
                ->references('uuid')->on('companies')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        DB::statement("ALTER TABLE `project_assignments` comment 'Asignaciones de proyectos a usuarios (conductores)'");
    }
};