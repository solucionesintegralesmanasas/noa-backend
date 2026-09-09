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
        Schema::create('enabling_resolutions', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la resolución');
            $table->uuid('uuid')->unique()->comment('UUID universal de la resolución');
            $table->uuid('company_uuid')->comment('UUID de la empresa autorizada');
            $table->string('resolution_number', 20)->comment('Número oficial de la resolución');
            $table->string('number_fuec', 20)->comment('Número FUEC (Formulario Único Electrónico de Comunicación)');
            $table->string('territorial_code', 20)->comment('Código territorial asignado');
            $table->date('resolution_date')->comment('Fecha de emisión o publicación');
            $table->boolean('status')->default(1)->comment('Estado de vigencia de la resolución');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enabling_resolutions');
    }
};
