<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consecutivo de contratos compartido por empresa y tipo de acción/contrato
 * en los contratos de prestación. Los dos formularios de radicación (admin
 * flota y prestación) consumen la misma secuencia por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_sequences', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_uuid')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('service_provision_contracts', function (Blueprint $table) {
            // El consecutivo es por empresa: el número solo debe ser único dentro
            // de cada empresa, no globalmente.
            $table->dropUnique('service_provision_contracts_contract_number_unique');
            $table->enum('type_of_action', ['C', 'M', 'E'])->default('C')->after('contract_number');
            $table->enum('contract_type', ['1', '2'])->default('2')->after('type_of_action');
            $table->unique(['company_uuid', 'contract_number']);
        });
    }

    public function down(): void
    {
        Schema::table('service_provision_contracts', function (Blueprint $table) {
            $table->dropUnique(['company_uuid', 'contract_number']);
            $table->dropColumn(['type_of_action', 'contract_type']);
            $table->unique('contract_number');
        });

        Schema::dropIfExists('contract_sequences');
    }
};
