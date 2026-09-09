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
        Schema::create('tax_information', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro tributario');
            $table->uuid('uuid')->unique()->comment('UUID único del registro');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->boolean('is_withholding_agent_exempt')->nullable()->comment('Indica si es agente de retención o está exento');
            $table->string('tax_special_regime', 255)->nullable()->comment('Ley o régimen tributario especial aplicable');
            $table->string('company_size', 50)->nullable()->comment('Clasificación de tamaño (Mipyme, mediana, grande, etc.)');
            $table->string('financial_statements_path', 255)->nullable()->comment('Ruta del archivo de estados financieros del último cierre');
            $table->string('company_size_certificate_path', 255)->nullable()->comment('Ruta del certificado de tamaño empresarial');
            $table->text('remarks')->nullable()->comment('Observaciones generales de carácter tributario');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_information');
    }
};
