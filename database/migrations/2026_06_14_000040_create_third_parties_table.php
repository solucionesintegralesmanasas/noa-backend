<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::create('third_parties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('uk_uuid');
            $table->uuid('company_uuid');
            $table->enum('person_type', ['NATURAL', 'JURIDICA']);
            $table->uuid('document_type_uuid');
            $table->string('document_number', 20);
            $table->char('nit_check_digit', 1)->nullable();
            $table->string('trade_name', 200)->nullable();
            $table->string('company_name', 200)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address', 200)->nullable();
            $table->uuid('municipality_uuid')->index('idx_municipality');
            $table->enum('tax_regime', ['48', '49', '47', '05', '42']);
            $table->uuid('tax_responsibility_uuid')->index('idx_tax_responsibility');
            $table->string('bank_account_number', 50)->nullable();
            $table->enum('bank_account_type', ['AHORROS', 'CORRIENTE', 'MONEDA_EXTRANJERA'])->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->uuid('cost_center_uuid')->nullable()->index('idx_cost_center');
            $table->boolean('is_customer')->default(0);
            $table->boolean('is_supplier')->default(0);
            $table->boolean('is_employee')->default(0);
            $table->boolean('is_affiliate')->default(0);
            $table->boolean('is_driver')->default(0);
            $table->boolean('is_others')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->unique(['company_uuid', 'document_type_uuid', 'document_number'], 'uq_company_doc');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('third_parties');
    }
};
