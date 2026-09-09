<?php

namespace Database\Seeders;

use App\Models\DianParameter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DianParameterSeeder extends Seeder
{
    public function run(): void
    {
        DianParameter::firstOrCreate(
            ['year' => 2026],
            [
                'uuid' => Str::uuid()->toString(),
                'uvt' => 51300.00, // Valor oficial UVT 2026
                'iva_withholding_rate' => 0.15, // Retención IVA (ejemplo)
                'minimum_wage' => 1300000.00, // Salario mínimo 2026
                'transport_subsidy' => 162000.00, // Subsidio transporte 2026
                'usury_rate' => 0.29, // Tasa de usura aproximada (varía mensualmente)
                'observations' => 'Parámetros DIAN 2026 oficiales',
            ]
        );
    }
}
