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
        Schema::create('companies', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la empresa');
            $table->uuid('uuid')->unique()->comment('UUID universal único de la empresa');
            $table->enum('person_type', ['PERSONA NATURAL', 'PERSONA JURIDICA'])->comment('Tipo de persona jurídica o natural');
            $table->enum('type_of_company', ['PUBLICO', 'PRIVADO'])->comment('Naturaleza de la compañía');
            $table->string('economic_sector', 255)->nullable()->comment('Sector económico de operación');
            $table->enum('legal_structure', ['SOCIEDAD POR ACCIONES SIMPLIFICADA - SAS', 'SOCIEDAD DE RESPONSABILIDAD LIMITADA - LTDA', 'SOCIEDAD POR ACCIONES - SPA', 'SOCIEDAD ANONIMA - SA', 'UNIÓN TEMPORAL - UT', 'ENTIDAD SIN ÁNIMO DE LUCRO - ESAL'])->nullable()->comment('Estructura legal registrada');
            $table->uuid('document_type_uuid')->comment('UUID del tipo de documento de la empresa');
            $table->string('document_number', 20)->comment('Número de documento único');
            $table->string('verification_digit', 1)->nullable()->comment('Dígito de verificación del NIT');
            $table->string('business_name', 200)->comment('Razón social legal completa');
            $table->string('trade_name', 100)->nullable()->comment('Nombre comercial o marca');
            $table->string('commercial_registration', 50)->nullable()->comment('Matrícula mercantil en Cámara de Comercio');
            $table->uuid('municipality_uuid')->comment('UUID del municipio de domicilio');
            $table->string('address', 200)->comment('Dirección física completa');
            $table->string('postal_code', 10)->nullable()->comment('Código postal de la dirección');
            $table->string('phone', 20)->nullable()->comment('Teléfono corporativo principal');
            $table->string('email', 100)->comment('Correo electrónico corporativo');
            $table->uuid('tax_regime_uuid')->comment('UUID del régimen fiscal asignado');
            $table->char('currency_code', 3)->nullable()->default('COP')->comment('Código ISO 4217 de moneda principal');
            $table->char('approximate_number_of_employees', 10)->nullable()->comment('Rango o número aproximado de empleados');
            $table->string('web_page', 255)->comment('Sitio web oficial');
            $table->char('country_code', 2)->nullable()->default('CO')->comment('Código ISO 3166-1 alfa-2 del país');
            $table->string('legal_representative_name', 255)->comment('Nombre(s) del representante legal');
            $table->string('legal_representative_last_name', 255)->comment('Apellido(s) del representante legal');
            $table->string('legal_representative_document_type', 20)->comment('Tipo de documento del representante legal');
            $table->string('legal_representative_document_number', 20)->comment('Número de documento del representante legal');
            $table->string('legal_representative_nationality', 20)->nullable()->comment('Nacionalidad del representante legal');
            $table->date('legal_representative_document_issue_date')->nullable()->comment('Fecha de expedición del documento');
            $table->boolean('is_active')->default(1)->comment('Estado de habilitación de la empresa (1=activo, 0=inactivo)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
