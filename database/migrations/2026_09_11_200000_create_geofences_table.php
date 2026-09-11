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

        Schema::create('geofences', function (Blueprint $table) {
            $table->id()->comment('ID único de la geocerca');
            $table->uuid('uuid')->unique()->comment('UUID único universal de la geocerca');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->string('name', 255)->comment('Nombre de la geocerca');
            $table->text('description')->nullable()->comment('Descripción de la geocerca');
            $table->enum('type', ['circle', 'polygon'])->default('circle')->comment('Tipo de geocerca (círculo o polígono)');
            $table->decimal('center_lat', 10, 8)->nullable()->comment('Latitud del centro');
            $table->decimal('center_lng', 11, 8)->nullable()->comment('Longitud del centro');
            $table->unsignedInteger('radius_meters')->nullable()->comment('Radio en metros');
            $table->json('polygon_points')->nullable()->comment('Puntos del polígono');
            $table->boolean('alert_on_enter')->default(1)->comment('Alertar al entrar');
            $table->boolean('alert_on_exit')->default(1)->comment('Alertar al salir');
            $table->unsignedInteger('max_speed_kmh')->nullable()->comment('Velocidad máxima permitida');
            $table->boolean('is_active')->default(1)->comment('Indica si la geocerca está activa');
            $table->timestamps();

            $table->index('company_uuid', 'idx_geofences_company');
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
        Schema::dropIfExists('geofences');
        Schema::enableForeignKeyConstraints();
    }
};