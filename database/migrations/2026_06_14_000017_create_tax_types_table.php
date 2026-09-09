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
        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 ÚNICO DEL TIPO DE IMPUESTO')->unique();
            $table->string('code', 10)->unique()->comment('CÓDIGO DIAN DEL IMPUESTO (01:IVA, 02:ICA, 03:INC, 04:TIMBRE, 05:BOLSA, ETC.)');
            $table->string('name', 100)->comment('NOMBRE COMPLETO DEL IMPUESTO');
            $table->enum('type', ['IVA', 'INC', 'ICA', 'TIMBRE', 'FOMENTO', 'OTRO'])->comment('CLASIFICACIÓN TRIBUTARIA DEL IMPUESTO');
            $table->decimal('rate', 6, 4)->comment('TARIFA PORCENTUAL APLICADA (EJ: 0.1900 = 19%)');
            $table->string('debit_account', 20)->nullable()->comment('CUENTA PUC PARA REGISTRAR EL DÉBITO DEL IMPUESTO');
            $table->string('credit_account', 20)->nullable()->comment('CUENTA PUC PARA REGISTRAR EL CRÉDITO DEL IMPUESTO');
            $table->boolean('applies_sales')->default(1)->comment('INDICA SI EL IMPUESTO APLICA EN VENTAS/FACTURACIÓN');
            $table->boolean('applies_purchases')->default(1)->comment('INDICA SI EL IMPUESTO APLICA EN COMPRAS/PROVEEDORES');
            $table->boolean('is_active')->default(1)->comment('INDICA SI LA CONFIGURATION DEL IMPUESTO ESTÁ VIGENTE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_types');
    }
};
