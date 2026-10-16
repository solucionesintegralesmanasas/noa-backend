<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE procedures MODIFY COLUMN procedure_type ENUM('CARTA_DE_ACEPTACION','CAPACIDAD_TRANSPORTADORA','INCLUSION_DE_POLIZAS','TARJETA_DE_OPERACION','DESVINCULACION','RENOVACION_TARJETA','DESVINCULACION_MUTUO','DESVINCULACION_UNILATERAL')");

        Schema::table('procedures', function (Blueprint $table) {
            $table->boolean('simit_clear')->nullable()->after('global_status')->comment('Sin comparendos SIMIT o con acuerdo de pago');
            $table->boolean('soat_valid')->nullable()->after('simit_clear');
            $table->boolean('rtm_valid')->nullable()->after('soat_valid');
            $table->string('old_card_number', 20)->nullable()->after('rtm_valid');
            $table->string('radicado_number', 50)->nullable()->after('old_card_number');
            $table->string('payment_status', 30)->nullable()->default('PENDIENTE')->after('radicado_number');
            $table->string('resolution_number', 50)->nullable()->after('payment_status');
        });

        Schema::table('system_configuration', function (Blueprint $table) {
            $table->uuid('default_territorial_director_uuid')->nullable()->after('corporate_rce_expiration')->comment('Dirección territorial por defecto para radicaciones');
            $table->string('rcc_insurer_company', 150)->nullable()->after('default_territorial_director_uuid')->comment('Empresa contratada para póliza RCC');
            $table->string('rce_insurer_company', 150)->nullable()->after('rcc_insurer_company')->comment('Empresa contratada para póliza RCE');
            $table->string('rcc_policy_number', 100)->nullable()->after('rce_insurer_company');
            $table->string('rce_policy_number', 100)->nullable()->after('rcc_policy_number');
        });
    }

    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn(['default_territorial_director_uuid', 'rcc_insurer_company', 'rce_insurer_company', 'rcc_policy_number', 'rce_policy_number']);
        });
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn(['simit_clear', 'soat_valid', 'rtm_valid', 'old_card_number', 'radicado_number', 'payment_status', 'resolution_number']);
        });
        // Antes de estrechar el ENUM hay que devolver los valores nuevos a uno
        // válido del enum antiguo, o el ALTER falla con STRICT_TRANS_TABLES.
        DB::statement("UPDATE procedures SET procedure_type = 'TARJETA_DE_OPERACION' WHERE procedure_type = 'RENOVACION_TARJETA'");
        DB::statement("UPDATE procedures SET procedure_type = 'DESVINCULACION' WHERE procedure_type IN ('DESVINCULACION_MUTUO','DESVINCULACION_UNILATERAL')");
        DB::statement("ALTER TABLE procedures MODIFY COLUMN procedure_type ENUM('CARTA_DE_ACEPTACION','CAPACIDAD_TRANSPORTADORA','INCLUSION_DE_POLIZAS','TARJETA_DE_OPERACION','DESVINCULACION')");
    }
};
