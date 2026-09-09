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
        Schema::create('contractors', function (Blueprint $table) {
            $table->id()->comment('Identificador único del contratista');
            $table->uuid('uuid')->unique()->comment('UUID único universal del contratista');
            $table->uuid('company_uuid')->index()->comment('UUID único universal de la empresa');
            $table->uuid('document_type_uuid')->index()->comment('UUID único universal del tipo de documento');
            $table->string('document_number', 20)->index()->comment('Número de documento del contratista');
            $table->string('company_name')->comment('Nombre o razón social del contratista');
            $table->string('address')->comment('Dirección del contratista');
            $table->string('telephone', 20)->comment('Teléfono del contratista');
            $table->string('contract_number', 20)->comment('Número de contrato del contratista');
            $table->string('contracting_party_city')->comment('Ciudad de contratación del contratista');
            $table->uuid('vehicle_uuid')->index()->comment('UUID único universal del vehículo');
            $table->string('responsible_name')->comment('Nombre del responsable del contratista');
            $table->string('responsible_document', 20)->comment('Número de documento del responsable del contratista');
            $table->string('responsible_phone', 20)->comment('Teléfono del responsable del contratista');
            $table->string('responsible_address')->comment('Dirección del responsable del contratista');
            $table->boolean('status')->default(1)->index()->comment('Estado del contratista');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
