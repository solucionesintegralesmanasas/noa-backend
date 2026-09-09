<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxRegimesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxRegimes = [
            [
                'code' => '48',
                'name' => 'Responsable del Impuesto sobre las ventas - IVA',
                'description' => 'Régimen común del impuesto sobre las ventas',
            ],
            [
                'code' => '49',
                'name' => 'No responsable de IVA',
                'description' => 'Personas no responsables del impuesto sobre las ventas',
            ],
            [
                'code' => '05',
                'name' => 'Régimen Simplificado',
                'description' => 'Régimen simplificado del impuesto sobre las ventas',
            ],
            [
                'code' => '06',
                'name' => 'Persona natural sin actividad económica',
                'description' => 'Persona natural que no realiza actividades económicas',
            ],
            [
                'code' => '07',
                'name' => 'Régimen simple de tributación - SIMPLE',
                'description' => 'Régimen simple de tributación para pequeñas empresas',
            ],
            [
                'code' => '08',
                'name' => 'Otros',
                'description' => 'Otros regímenes no especificados',
            ],
        ];

        foreach ($taxRegimes as $regime) {
            DB::table('tax_regimes')->insertOrIgnore([
                'uuid' => Str::uuid(),
                'code' => $regime['code'],
                'name' => $regime['name'],
                'description' => $regime['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Regímenes tributarios insertados correctamente.');
    }
}
