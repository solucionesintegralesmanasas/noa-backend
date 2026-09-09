<?php

namespace Database\Seeders;

use App\Models\TaxResponsibility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxResponsibilitySeeder extends Seeder
{
    public function run(): void
    {
        $responsibilities = [
            ['code' => 'O-13', 'name' => 'Gran contribuyente'],
            ['code' => 'O-15', 'name' => 'Autorretenedor'],
            ['code' => 'O-23', 'name' => 'Agente de retención IVA'],
            ['code' => 'O-47', 'name' => 'Régimen Simple de Tributación - SIMPLE'],
            ['code' => 'R-99-PN', 'name' => 'No responsable (Persona Natural)'],
        ];

        foreach ($responsibilities as $res) {
            TaxResponsibility::firstOrCreate(
                ['code' => $res['code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $res['name'],
                ]
            );
        }
    }
}
