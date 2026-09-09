<?php

namespace Database\Seeders;

use App\Models\Tribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TributeSeeder extends Seeder
{
    public function run(): void
    {

        $tributes = [
            ['dian_code' => '01', 'name' => 'IVA', 'description' => 'Impuesto sobre las Ventas'],
            ['dian_code' => '02', 'name' => 'IC', 'description' => 'Impuesto al Consumo'],
            ['dian_code' => '03', 'name' => 'ICA', 'description' => 'Impuesto de Industria y Comercio'],
            ['dian_code' => '04', 'name' => 'INC', 'description' => 'Impuesto Nacional al Consumo'],
            ['dian_code' => '05', 'name' => 'ReteIVA', 'description' => 'Retención sobre el IVA'],
            ['dian_code' => '06', 'name' => 'ReteFuente', 'description' => 'Retención en la Fuente por Renta'], // Actualizado al estándar DIAN
            ['dian_code' => '07', 'name' => 'ReteICA', 'description' => 'Retención sobre el ICA'],
            ['dian_code' => '22', 'name' => 'INC Bolsas', 'description' => 'Impuesto Nacional al Consumo de Bolsas Plásticas'],
            ['dian_code' => '34', 'name' => 'IBUA', 'description' => 'Impuesto a las Bebidas Ultraprocesadas Azucaradas'], // Nuevo impuesto
            ['dian_code' => '35', 'name' => 'ICUI', 'description' => 'Impuesto a los Productos Comestibles Ultraprocesados'], // Nuevo impuesto
            ['dian_code' => 'ZZ', 'name' => 'No aplica', 'description' => 'No aplica tributo'],
        ];

        foreach ($tributes as $tribute) {
            Tribute::firstOrCreate(
                ['dian_code' => $tribute['dian_code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $tribute['name'],
                    'description' => $tribute['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
