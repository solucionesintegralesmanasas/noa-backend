<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La radicación de tarjeta de operación necesita asociar el contrato de
 * administración de flota al vehículo, al tercero afiliado y a la empresa,
 * igual que hace el contrato de prestación de servicios. Además vuelve
 * opcionales los tres campos técnicos que solo alimentaban el Portal TO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_service_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_service_contracts', 'company_uuid')) {
                $table->uuid('company_uuid')->nullable()->after('procedure_uuid')->index()
                    ->comment('Empresa propietaria del contrato');
            }
            if (! Schema::hasColumn('fleet_service_contracts', 'vehicle_uuid')) {
                $table->uuid('vehicle_uuid')->nullable()->after('company_uuid')->index()
                    ->comment('Vehículo al que se asigna el contrato');
            }
            if (! Schema::hasColumn('fleet_service_contracts', 'third_party_uuid')) {
                $table->uuid('third_party_uuid')->nullable()->after('vehicle_uuid')->index()
                    ->comment('Propietario o tercero afiliado');
            }
        });

        // 'item', 'type_of_action' y 'contract_type' quedan con valor por defecto.
        \DB::statement('ALTER TABLE fleet_service_contracts MODIFY item INT NULL');
        \DB::statement("ALTER TABLE fleet_service_contracts MODIFY type_of_action ENUM('C','M','E') NULL");
        \DB::statement("ALTER TABLE fleet_service_contracts MODIFY contract_type ENUM('1','2') NULL");
        \DB::statement('ALTER TABLE fleet_service_contracts MODIFY valuation_amount DECIMAL(10,2) NULL');
    }

    public function down(): void
    {
        Schema::table('fleet_service_contracts', function (Blueprint $table) {
            $table->dropColumn(['company_uuid', 'vehicle_uuid', 'third_party_uuid']);
        });

        \DB::statement('ALTER TABLE fleet_service_contracts MODIFY item INT NOT NULL');
        \DB::statement("ALTER TABLE fleet_service_contracts MODIFY type_of_action ENUM('C','M','E') NOT NULL");
        \DB::statement("ALTER TABLE fleet_service_contracts MODIFY contract_type ENUM('1','2') NOT NULL");
        \DB::statement('ALTER TABLE fleet_service_contracts MODIFY valuation_amount DECIMAL(10,2) NOT NULL');
    }
};
