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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id()->comment('Identificador único del contacto');
            $table->uuid('uuid')->unique()->comment('UUID universal único del contacto');
            $table->uuid('company_uuid')->comment('UUID de la empresa a la que pertenece el contacto');
            $table->string('first_name', 100)->nullable()->comment('Nombres del contacto');
            $table->string('last_name', 100)->nullable()->comment('Apellidos del contacto');
            $table->enum('representative_type', ['COMERCIAL', 'REPRESENTANTE_LEGAL', 'TECNICO', 'CARTERA', 'COMPRAS', 'CONTACTO_FACTURACION', 'CUMPLIMIENTO', 'FINANCIERO', 'HSE', 'JURIDICO', 'REPRESENTANTE_LEGAL_SUPLENTE'])->comment('Cargo o función del contacto en la organización');
            $table->string('country', 100)->nullable()->comment('País de residencia o ubicación');
            $table->uuid('municipality_uuid')->nullable()->comment('UUID del municipio de ubicación');
            $table->string('email', 150)->nullable()->comment('Correo electrónico de contacto');
            $table->string('phone_number', 50)->nullable()->comment('Número de teléfono de contacto');
            $table->text('remarks')->nullable()->comment('Observaciones o notas adicionales sobre el contacto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
