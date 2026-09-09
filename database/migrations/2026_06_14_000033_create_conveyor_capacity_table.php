<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conveyor_capacity', function (Blueprint $table) {
            $table->id()->comment('Identificador único de capacidad');
            $table->uuid('uuid')->unique()->comment('UUID universal de la capacidad');
            $table->uuid('enabling_resolution_uuid')->comment('UUID de la resolución habilitante');
            $table->string('vehicle_type', 20)->comment('Tipo o categoría de vehículo autorizado');
            $table->integer('authorized_capacity')->comment('Capacidad máxima autorizada');
            $table->integer('current_capacity')->comment('Capacidad operativa actual');
            $table->integer('minimum_own_capacity')->comment('Capacidad mínima propia exigida');
            $table->boolean('status')->default(1)->comment('Estado de habilitación');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conveyor_capacity');
    }
};
