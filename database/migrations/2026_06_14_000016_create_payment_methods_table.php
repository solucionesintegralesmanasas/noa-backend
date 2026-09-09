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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 ÚNICO DEL MÉTODO DE PAGO')->unique();
            $table->string('dian_code', 5)->unique()->comment('CÓDIGO DIAN PARA FACTURACIÓN ELECTRÓNICA (10:EFECTIVO, 42:CONSIGNACIÓN, 48:TARJETA, ETC.)');
            $table->string('name', 100)->comment('NOMBRE DESCRIPTIVO DEL MEDIO DE PAGO');
            $table->boolean('is_active')->default(1)->comment('INDICA SI EL MÉTODO DE PAGO ESTÁ HABILITADO PARA SU USO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
