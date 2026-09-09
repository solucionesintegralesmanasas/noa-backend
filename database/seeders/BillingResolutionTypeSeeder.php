<?php

namespace Database\Seeders;

use App\Models\BillingResolutionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BillingResolutionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => '01', 'name' => 'Factura electrónica de venta'],
            ['code' => '03', 'name' => 'Instrumento electrónico de transmisión - tipo 03.'],
            ['code' => '91', 'name' => 'Documento Soporte en Adquisiciones Efectuadas a No Obligados a Facturar'],
        ];

        foreach ($types as $type) {
            BillingResolutionType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $type['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
