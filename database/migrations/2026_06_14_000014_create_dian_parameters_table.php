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
        Schema::create('dian_parameters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 ÚNICO PARA LOS PARÁMETROS')->unique();
            $table->smallInteger('year')->comment('AÑO FISCAL AL QUE APLICAN LOS PARÁMETROS')->unique();
            $table->decimal('uvt', 10, 2)->comment('VALOR DE LA UNIDAD DE VALOR TRIBUTARIO (UVT) PARA EL AÑO');
            $table->decimal('iva_withholding_rate', 6, 4)->default(0.15)->comment('PORCENTAJE ESTÁNDAR DE RETENCIÓN DE IVA (EJ: 0.15 = 15%)');
            $table->decimal('minimum_wage', 12, 2)->nullable()->comment('SALARIO MÍNIMO LEGAL VIGENTE MENSUAL PARA EL AÑO');
            $table->decimal('transport_subsidy', 12, 2)->nullable()->comment('AUXILIO DE TRANSPORTE MENSUAL PARA EL AÑO');
            $table->decimal('usury_rate', 6, 4)->nullable()->comment('TASA MÁXIMA DE USURA CERTIFICADA POR LA SUPERFINANCIERA');
            $table->text('observations')->nullable()->comment('OBSERVACIONES O NOTAS LEGALES APLICABLES AL AÑO');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `dian_parameters` COMMENT = 'Parámetros tributarios y económicos anuales de referencia DIAN'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dian_parameters');
    }
};
