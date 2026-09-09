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
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('link_type', ['CAMBIO_DE_EMPRESA', 'NUEVO_VEHICULO'])->nullable();
            $table->uuid('company_uuid')->nullable();
            $table->uuid('third_party_uuid')->nullable();
            $table->uuid('vehicle_uuid')->nullable();
            $table->string('procedure_code', 50);
            $table->string('filed_number', 50)->nullable();
            $table->enum('procedure_type', [
                'CARTA_DE_ACEPTACION',
                'CAPACIDAD_TRANSPORTADORA',
                'INCLUSION_DE_POLIZAS',
                'TARJETA_DE_OPERACION',
                'DESVINCULACION',
            ]);
            $table->date('date_of_creation');
            $table->uuid('city_uuid');
            $table->string('subject', 255)->nullable();
            $table->uuid('territorial_director_uuid');
            $table->enum('status', [
                'RECIBIDO',
                'EN_PROCESO',
                'COMPLETADO',
                'CANCELADO',
            ])->default('RECIBIDO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
