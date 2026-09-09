<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            ['description' => 'RENAULT'],
            ['description' => 'KIA'],
            ['description' => 'HYUNDAI'],
            ['description' => 'DFSK'],
            ['description' => 'JAC'],
            ['description' => 'MERCEDES BENZ'],
            ['description' => 'FORD'],
            ['description' => 'MG'],
            ['description' => 'TOYOTA'],
            ['description' => 'NISSAN'],
            ['description' => 'CHEVROLET'],
            ['description' => 'MAZDA'],
            ['description' => 'VOLKSWAGEN'],
            ['description' => 'SUZUKI'],
            ['description' => 'MITSUBISHI'],
            ['description' => 'CHERY'],
            ['description' => 'BYD'],
            ['description' => 'GEELY'],
            ['description' => 'PEUGEOT'],
            ['description' => 'CITROEN'],
            ['description' => 'IVECO'],
            ['description' => 'HINO'],
            ['description' => 'YUTONG'],
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->insert([
                'uuid' => (string) Str::uuid(),
                'description' => $brand['description'],
            ]);
        }
    }
}
