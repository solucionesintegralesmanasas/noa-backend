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
        Schema::create('fuec', function (Blueprint $table) {
            $table->id()->comment('Identificador único del FUEC');
            $table->uuid('uuid')->unique()->comment('UUID único universal del FUEC');
            $table->date('issue_date')->comment('Fecha de expedición del FUEC');
            $table->string('request_number', 25)->unique()->comment('Número de solicitud del FUEC');
            $table->string('number_fuec', 4)->comment('Número del FUEC');
            $table->string('contract_number_display', 4)->comment('Número de contrato del FUEC');
            $table->uuid('company_uuid')->index()->comment('UUID único universal de la empresa');
            $table->uuid('contractor_uuid')->index()->comment('UUID único universal del contratista');
            $table->uuid('vehicle_uuid')->index()->comment('UUID único universal del vehículo');
            $table->date('effective_date')->comment('Fecha de inicio del FUEC');
            $table->date('expiration_date')->comment('Fecha de fin del FUEC');
            $table->string('origin_route')->comment('Ruta de origen del FUEC');
            $table->string('destination_route')->comment('Ruta de destino del FUEC');
            $table->uuid('object_contract_uuid')->index()->comment('UUID único universal del objeto de contrato');
            $table->uuid('main_conductor_uuid')->index()->comment('UUID único universal del conductor principal');
            $table->uuid('secondary_conductor_uuid')->nullable()->index()->comment('UUID único universal del conductor secundario');
            $table->uuid('tertiary_conductor_uuid')->nullable()->index()->comment('UUID único universal del conductor terciario');
            $table->string('verification_code', 100)->comment('Código de verificación del FUEC');
            $table->enum('status', ['ACTIVO', 'CERRADO', 'ANULADO'])->default('ACTIVO')->comment('Estado del FUEC');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuec');
    }
};
