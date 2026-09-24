<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fija los nombres legales (business_name y trade_name) como nombres de
     * empresa propia en las configuraciones existentes que no los tengan.
     * Las variantes que aparecen en las tarjetas (texto libre) se administran
     * desde la UI de Configuración del sistema.
     */
    public function up(): void
    {
        $companies = DB::table('companies')->select('uuid', 'business_name', 'trade_name')->get();

        foreach ($companies as $company) {
            $names = array_values(array_unique(array_filter([
                trim((string) $company->business_name),
                trim((string) $company->trade_name),
            ])));

            if (empty($names)) {
                continue;
            }

            DB::table('system_configuration')
                ->where('company_uuid', $company->uuid)
                ->whereNull('own_company_names')
                ->update([
                    'own_company_names' => json_encode($names),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Sin reversa: es una carga inicial de datos, no un cambio de esquema.
     */
    public function down(): void
    {
        // Sin operación.
    }
};
