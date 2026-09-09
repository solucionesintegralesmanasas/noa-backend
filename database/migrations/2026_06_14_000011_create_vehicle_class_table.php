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
        Schema::create('vehicle_class', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID UNIVERSAL ÚNICO DE LA CLASE DE VEHÍCULO');
            $table->char('class_code_class', 5)->comment('CÓDIGO INTERNO CORTO DE LA CLASE DE VEHÍCULO');
            $table->string('description', 200)->comment('DESCRIPCIÓN TÉCNICA DE LA CLASE DE VEHÍCULO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_class');
    }
};
