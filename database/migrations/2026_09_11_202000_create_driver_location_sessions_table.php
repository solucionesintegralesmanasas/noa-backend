<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('driver_location_sessions', function (Blueprint $table) {
            $table->id()->comment('ID único de la sesión de rastreo');
            $table->uuid('uuid')->unique()->comment('UUID único universal de la sesión');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('third_party_uuid')->comment('UUID único universal del conductor');
            $table->uuid('vehicle_uuid')->nullable()->comment('UUID único universal del vehículo');
            $table->uuid('project_uuid')->nullable()->comment('UUID único universal del proyecto');
            $table->timestamp('started_at')->useCurrent()->comment('Fecha y hora de inicio de la sesión');
            $table->timestamp('ended_at')->nullable()->comment('Fecha y hora de fin de la sesión');
            $table->enum('status', ['active', 'paused', 'ended'])->default('active')->comment('Estado de la sesión');
            $table->decimal('total_distance_km', 10, 2)->default(0)->comment('Distancia total recorrida en km');
            $table->unsignedInteger('total_points')->default(0)->comment('Total de puntos GPS registrados');
            $table->timestamps();

            $table->index(['company_uuid', 'status'], 'idx_dls_company_status');
            $table->index(['third_party_uuid', 'status'], 'idx_dls_driver_status');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('driver_location_sessions');
        Schema::enableForeignKeyConstraints();
    }
};