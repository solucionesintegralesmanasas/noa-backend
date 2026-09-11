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

        Schema::create('driver_location_alerts', function (Blueprint $table) {
            $table->id()->comment('ID único de la alerta');
            $table->uuid('uuid')->unique()->comment('UUID único universal de la alerta');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('third_party_uuid')->comment('UUID único universal del conductor');
            $table->uuid('driver_location_uuid')->nullable()->comment('UUID de la ubicación que generó la alerta');
            $table->enum('alert_type', ['geofence_enter', 'geofence_exit', 'overspeed', 'idle', 'panic'])->comment('Tipo de alerta');
            $table->uuid('geofence_uuid')->nullable()->comment('UUID de la geocerca relacionada');
            $table->text('message')->comment('Mensaje descriptivo de la alerta');
            $table->decimal('latitude', 10, 8)->comment('Latitud donde ocurrió');
            $table->decimal('longitude', 11, 8)->comment('Longitud donde ocurrió');
            $table->boolean('is_read')->default(0)->comment('Indica si la alerta fue leída');
            $table->timestamps();

            $table->index(['company_uuid', 'is_read'], 'idx_dla_company_read');
            $table->index('third_party_uuid', 'idx_dla_driver');
            $table->index('created_at', 'idx_dla_created_at');
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
        Schema::dropIfExists('driver_location_alerts');
        Schema::enableForeignKeyConstraints();
    }
};