<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['dane_code' => '05', 'name' => 'Antioquia'],
            ['dane_code' => '08', 'name' => 'Atlántico'],
            ['dane_code' => '11', 'name' => 'Bogotá D.C.'],
            ['dane_code' => '13', 'name' => 'Bolívar'],
            ['dane_code' => '15', 'name' => 'Boyacá'],
            ['dane_code' => '17', 'name' => 'Caldas'],
            ['dane_code' => '18', 'name' => 'Caquetá'],
            ['dane_code' => '19', 'name' => 'Cauca'],
            ['dane_code' => '20', 'name' => 'Cesar'],
            ['dane_code' => '23', 'name' => 'Córdoba'],
            ['dane_code' => '25', 'name' => 'Cundinamarca'],
            ['dane_code' => '27', 'name' => 'Chocó'],
            ['dane_code' => '41', 'name' => 'Huila'],
            ['dane_code' => '44', 'name' => 'La Guajira'],
            ['dane_code' => '47', 'name' => 'Magdalena'],
            ['dane_code' => '50', 'name' => 'Meta'],
            ['dane_code' => '52', 'name' => 'Nariño'],
            ['dane_code' => '54', 'name' => 'Norte de Santander'],
            ['dane_code' => '63', 'name' => 'Quindío'],
            ['dane_code' => '66', 'name' => 'Risaralda'],
            ['dane_code' => '68', 'name' => 'Santander'],
            ['dane_code' => '70', 'name' => 'Sucre'],
            ['dane_code' => '73', 'name' => 'Tolima'],
            ['dane_code' => '76', 'name' => 'Valle del Cauca'],
            ['dane_code' => '81', 'name' => 'Arauca'],
            ['dane_code' => '85', 'name' => 'Casanare'],
            ['dane_code' => '86', 'name' => 'Putumayo'],
            ['dane_code' => '88', 'name' => 'Archipiélago de San Andrés, Providencia y Santa Catalina'],
            ['dane_code' => '91', 'name' => 'Amazonas'],
            ['dane_code' => '94', 'name' => 'Guainía'],
            ['dane_code' => '95', 'name' => 'Guaviare'],
            ['dane_code' => '97', 'name' => 'Vaupés'],
            ['dane_code' => '99', 'name' => 'Vichada'],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->insertOrIgnore([
                'uuid' => Str::uuid(),
                'dane_code' => $department['dane_code'],
                'name' => $department['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Departamentos insertados correctamente.');
    }
}
