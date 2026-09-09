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
        Schema::create('financial_statements', function (Blueprint $table) {
            $table->id()->comment('Identificador único del reporte');
            $table->uuid('uuid')->unique()->comment('UUID universal de los estados financieros');
            $table->uuid('company_uuid')->comment('UUID de la empresa reportante');
            $table->integer('fiscal_year')->comment('Año del ejercicio fiscal');
            $table->string('currency', 10)->nullable()->default('COP')->comment('Moneda de reporte');
            $table->decimal('current_assets', 18, 2)->nullable()->comment('Activo corriente');
            $table->decimal('inventory', 18, 2)->nullable()->comment('Inventarios');
            $table->decimal('total_assets', 18, 2)->nullable()->comment('Activo total');
            $table->decimal('current_liabilities', 18, 2)->nullable()->comment('Pasivo corriente');
            $table->decimal('financial_obligations', 18, 2)->nullable()->comment('Obligaciones financieras');
            $table->decimal('total_liabilities', 18, 2)->nullable()->comment('Pasivo total');
            $table->decimal('retained_earnings', 18, 2)->nullable()->comment('Utilidades retenidas o acumuladas');
            $table->decimal('equity', 18, 2)->nullable()->comment('Patrimonio total');
            $table->decimal('operational_income', 18, 2)->nullable()->comment('Ingresos operacionales');
            $table->decimal('operating_profit_before_tax', 18, 2)->nullable()->comment('Utilidad operacional antes de impuestos');
            $table->decimal('net_income_period', 18, 2)->nullable()->comment('Utilidad neta del periodo');
            $table->decimal('depreciation_amortization', 18, 2)->nullable()->comment('Depreciación y amortización');
            $table->decimal('financial_expenses', 18, 2)->nullable()->comment('Gastos financieros');
            $table->text('remarks')->nullable()->comment('Observaciones de auditoría o revelaciones');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_statements');
    }
};
