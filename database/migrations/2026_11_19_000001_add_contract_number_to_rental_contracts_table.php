<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->string('contract_number', 10)->nullable()->after('company_uuid');
        });

        // Asignar consecutivo secuencial por empresa a los contratos ya existentes
        $contratos = DB::table('rental_contracts')
            ->select('id', 'company_uuid')
            ->orderBy('company_uuid')
            ->orderBy('id')
            ->get();

        $contadores = [];
        foreach ($contratos as $contrato) {
            $empresa = (string) $contrato->company_uuid;
            $contadores[$empresa] = ($contadores[$empresa] ?? 0) + 1;
            $numero = str_pad((string) $contadores[$empresa], 4, '0', STR_PAD_LEFT);

            DB::table('rental_contracts')
                ->where('id', $contrato->id)
                ->update(['contract_number' => $numero]);
        }

        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->unique(['company_uuid', 'contract_number'], 'rental_contracts_company_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rental_contracts', function (Blueprint $table) {
            $table->dropUnique('rental_contracts_company_number_unique');
            $table->dropColumn('contract_number');
        });
    }
};
