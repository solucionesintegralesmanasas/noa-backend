<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VehicleClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            ['class_code_class' => '1', 'description' => 'AUTOMOVIL'],
            ['class_code_class' => '2', 'description' => 'BUS'],
            ['class_code_class' => '3', 'description' => 'BUSETA'],
            ['class_code_class' => '4', 'description' => 'CAMION'],
            ['class_code_class' => '5', 'description' => 'CAMIONETA'],
            ['class_code_class' => '6', 'description' => 'CAMPERO'],
            ['class_code_class' => '7', 'description' => 'MICROBUS'],
            ['class_code_class' => '8', 'description' => 'TRACTOCAMION'],
            ['class_code_class' => '10', 'description' => 'MOTOCICLETA'],
            ['class_code_class' => '14', 'description' => 'MOTOCARRO'],
            ['class_code_class' => '17', 'description' => 'MOTOTRICICLO'],
            ['class_code_class' => '19', 'description' => 'CUATRIMOTO'],
            ['class_code_class' => '24', 'description' => 'REMOLQUE'],
            ['class_code_class' => '41', 'description' => 'SEMIREMOLQUE'],
            ['class_code_class' => '42', 'description' => 'VOLQUETA'],
            ['class_code_class' => '43', 'description' => 'SIN CLASE'],
            ['class_code_class' => '160', 'description' => 'MAQ. CONSTRUCCION O MINERA'],
            ['class_code_class' => '163', 'description' => 'CICLOMOTOR'],
            ['class_code_class' => '164', 'description' => 'TRICIMOTO'],
            ['class_code_class' => '165', 'description' => 'CUADRICICLO'],
        ];

        $now = now(); // Fecha y hora actual para created_at y updated_at

        foreach ($classes as $class) {
            DB::table('vehicle_class')->insert([
                'uuid' => (string) Str::uuid(),
                'class_code_class' => $class['class_code_class'],
                'description' => $class['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
