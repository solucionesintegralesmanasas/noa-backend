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

        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id()->comment('ID único del registro de ubicación');
            $table->uuid('uuid')->unique()->comment('UUID único universal del registro');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('third_party_uuid')->comment('UUID único universal del conductor');
            $table->uuid('vehicle_uuid')->nullable()->comment('UUID único universal del vehículo');
            $table->uuid('project_uuid')->nullable()->comment('UUID único universal del proyecto');
            $table->decimal('latitude', 10, 8)->comment('Latitud del punto');
            $table->decimal('longitude', 11, 8)->comment('Longitud del punto');
            $table->decimal('altitude', 10, 2)->nullable()->comment('Altitud en metros');
            $table->decimal('speed', 6, 2)->default(0)->comment('Velocidad en km/h');
            $table->decimal('heading', 5, 2)->nullable()->comment('Dirección del movimiento en grados');
            $table->decimal('accuracy', 8, 2)->nullable()->comment('Precisión en metros');
            $table->unsignedTinyInteger('battery_level')->nullable()->comment('Nivel de batería (0-100)');
            $table->boolean('is_moving')->default(0)->comment('Indica si el dispositivo está en movimiento');
            $table->enum('source', ['gps', 'network', 'fused'])->default('gps')->comment('Fuente de la ubicación');
            $table->timestamp('recorded_at')->useCurrent()->comment('Fecha y hora del registro GPS');
            $table->timestamps();

            $table->index(['company_uuid', 'recorded_at'], 'idx_dl_company_recorded');
            $table->index(['third_party_uuid', 'recorded_at'], 'idx_dl_driver_recorded');
            $table->index('vehicle_uuid', 'idx_dl_vehicle');
            $table->index('recorded_at', 'idx_dl_recorded_at');
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
        Schema::dropIfExists('driver_locations');
        Schema::enableForeignKeyConstraints();
    }
};