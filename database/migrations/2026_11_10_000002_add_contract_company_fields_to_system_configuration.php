<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la empresa para contratos, independientes del registro de Empresas.
 * La razón social, NIT, contacto y representante legal que se imprimen en los
 * contratos de administración de flota y de prestación de servicios viven aquí,
 * en la configuración del sistema, sin tocar la tabla `companies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->string('contract_company_name')->nullable()->after('notification_email');
            $table->string('contract_company_nit')->nullable()->after('contract_company_name');
            $table->string('contract_company_email')->nullable()->after('contract_company_nit');
            $table->string('contract_company_phone')->nullable()->after('contract_company_email');
            $table->string('contract_company_address')->nullable()->after('contract_company_phone');
            $table->string('contract_company_rep_name')->nullable()->after('contract_company_address');
            $table->string('contract_company_rep_last_name')->nullable()->after('contract_company_rep_name');
            $table->string('contract_company_rep_document')->nullable()->after('contract_company_rep_last_name');
        });
    }

    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn([
                'contract_company_name',
                'contract_company_nit',
                'contract_company_email',
                'contract_company_phone',
                'contract_company_address',
                'contract_company_rep_name',
                'contract_company_rep_last_name',
                'contract_company_rep_document',
            ]);
        });
    }
};
