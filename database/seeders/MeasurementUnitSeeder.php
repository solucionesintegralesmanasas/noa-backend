<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MeasurementUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => '94', 'name' => 'unidad'],
            ['code' => 'EA', 'name' => 'cada'],
            ['code' => 'ZZ', 'name' => 'mutuamente definido'],
            ['code' => 'KGM', 'name' => 'kilogramo'],
            ['code' => 'GRM', 'name' => 'gramo'],
            ['code' => 'LBR', 'name' => 'libra'],
            ['code' => 'LTR', 'name' => 'litro'],
            ['code' => 'MLT', 'name' => 'mililitro'],
            ['code' => 'GLL', 'name' => 'galón'],
            ['code' => 'MTR', 'name' => 'metro'],
            ['code' => 'CMT', 'name' => 'centímetro'],
            ['code' => 'MTK', 'name' => 'metro cuadrado'],
            ['code' => 'MTQ', 'name' => 'Metro cúbico'],
            ['code' => 'BX', 'name' => 'caja'],
            ['code' => 'PK', 'name' => 'paquete'],
            ['code' => 'DZN', 'name' => 'docena'],
            ['code' => 'SET', 'name' => 'conjunto'],
            ['code' => 'HUR', 'name' => 'hora'],
            ['code' => 'DAY', 'name' => 'día'],
        ];

        foreach ($units as $unit) {
            MeasurementUnit::firstOrCreate(
                ['code' => $unit['code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $unit['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
