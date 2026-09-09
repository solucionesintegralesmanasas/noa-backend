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
        Schema::create('withholdings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 ÚNICO DE LA RETENCIÓN')->unique();
            $table->string('code', 20)->unique()->comment('CÓDIGO INTERNO DE LA RETENCIÓN');
            $table->string('name', 150)->comment('DENOMINACIÓN OFICIAL DE LA RETENCIÓN');
            $table->enum('type', ['RETEFUENTE', 'RETEIVA', 'RETEICA', 'RETECREE', 'AUTORETENCIÓN'])->comment('TIPO DE RETENCIÓN SEGÚN CLASIFICACIÓN TRIBUTARIA');
            $table->string('dian_concept', 10)->nullable()->comment('CONCEPTO DIAN APLICABLE');
            $table->decimal('base_minimum', 18, 2)->default(0.00)->comment('BASE MÍNIMA EN COP PARA APLICAR LA RETENCIÓN');
            $table->decimal('rate', 8, 6)->comment('PORCENTAJE DE RETENCIÓN (EJ: 0.035000 = 3.5%)');
            $table->string('debit_account', 20)->nullable()->comment('CUENTA CONTABLE DE DÉBITO PARA LA RETENCIÓN');
            $table->string('credit_account', 20)->nullable()->comment('CUENTA CONTABLE DE CRÉDITO PARA LA RETENCIÓN');
            $table->boolean('applies_purchases')->default(1)->comment('INDICA SI SE RETIENE A PROVEEDORES EN COMPRAS');
            $table->boolean('applies_sales')->default(0)->comment('INDICA SI SE RETIENE A CLIENTES EN VENTAS');
            $table->boolean('is_active')->default(1)->comment('INDICA SI LA CONFIGURACIÓN DE RETENCIÓN ESTÁ ACTIVA');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholdings');
    }
};
