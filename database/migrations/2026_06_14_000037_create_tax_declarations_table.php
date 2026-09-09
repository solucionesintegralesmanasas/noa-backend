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
        Schema::create('tax_declarations', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la declaración');
            $table->uuid('uuid')->unique()->comment('UUID universal de la declaración de renta');
            $table->uuid('company_uuid')->comment('UUID de la empresa declarante');
            $table->integer('fiscal_year')->comment('Año gravable');
            $table->decimal('gross_assets', 18, 2)->nullable()->comment('Patrimonio bruto');
            $table->decimal('net_assets', 18, 2)->nullable()->comment('Patrimonio líquido');
            $table->decimal('total_gross_income', 18, 2)->nullable()->comment('Total ingresos brutos');
            $table->decimal('ordinary_net_income', 18, 2)->nullable()->comment('Renta líquida ordinaria');
            $table->decimal('pre_tax_net_profit', 18, 2)->nullable()->comment('Utilidad neta antes de impuestos');
            $table->decimal('total_operating_non_operating_income', 18, 2)->nullable()->comment('Total ingresos operacionales y no operacionales');
            $table->text('remarks')->nullable()->comment('Observaciones generales');
            $table->enum('status', ['BORRADOR', 'PRESENTADO'])->default('BORRADOR')->comment('Estado de la declaración');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_declarations');
    }
};
