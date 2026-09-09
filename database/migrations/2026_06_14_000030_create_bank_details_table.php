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
        Schema::create('bank_details', function (Blueprint $table) {
            $table->id()->comment('Identificador único del detalle bancario');
            $table->uuid('uuid')->unique()->comment('UUID único del detalle bancario');
            $table->uuid('company_uuid')->comment('UUID de la empresa propietaria');
            $table->string('bank_name', 100)->nullable()->comment('Nombre comercial del banco');
            $table->string('branch_office', 100)->nullable()->comment('Nombre de la sucursal bancaria');
            $table->string('account_type', 50)->nullable()->comment('Tipo de producto (ej. ahorros, corriente, fiduciaria)');
            $table->string('account_number', 50)->comment('Número de cuenta bancaria');
            $table->string('account_holder', 255)->nullable()->comment('Nombre completo del titular de la cuenta');
            $table->boolean('is_active')->default(1)->comment('Indica si la cuenta está habilitada para transacciones');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_details');
    }
};
