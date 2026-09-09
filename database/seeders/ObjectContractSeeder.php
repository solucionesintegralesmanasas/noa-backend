<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ObjectContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $objects = [
            [
                'name' => 'OBJETO DE CONTRATO GRUPO ESPECIFICO DE PERSONAS',
                'description' => 'PRESTAR EL SERVICIO DE TRANSPORTE ESPECIAL DE PASAJEROS, DE ACUERDO CONFORMIDAD CON EL NUMERAL 4° ART. 7° DEL DECRETO 478 DEL 2021, PARA EL GRUPO ESPECÍFICO DE PERSONAS CONTRATADOS POR EL CONTRATANTE HOMOGÉNEO.',
                'created_at' => '2023-05-10 09:25:09',
            ],
            [
                'name' => 'OBJETO DE CONTRATO GRUPO EMPRESARIAL',
                'description' => 'PRESTAR EL SERVICIO DE TRANSPORTE ESPECIAL DE PASAJEROS, DE ACUERDO CONFORMIDAD CON EL NUMERAL 4° ART. 7° DEL DECRETO 478 DEL 2021, EMPRESARIAL CONTRATADOS POR EL CONTRATANTE.',
                'created_at' => '2023-05-10 09:25:07',
            ],
            [
                'name' => 'OBJETO DE CONTRATO GRUPO PACIENTE',
                'description' => 'CONTRATO PARA EL TRANSPORTE DE USUARIO DEL SERVICIO DE SALUD, TRANSPORTE DEL MÉDICOS, FUNCIONARIOS Y PACIENTES MEDIANTE ART. 7° DEL DECRETO 478 DEL 2021 CONTRATADOS POR EL CONTRATANTE',
                'created_at' => '2023-05-10 09:25:04',
            ],
            [
                'name' => 'OBJETO DE CONTRATO GRUPO ESCOLAR',
                'description' => 'PRESTAR EL SERVICIO DE TRANSPORTE ESPECIAL DE ESTUDIANTES, DE ACUERDO CONFORMIDAD CON EL NUMERAL 4° ART. 7° DEL DECRETO 478 DEL 2021, CONTRATADOS POR EL CONTRATANTE',
                'created_at' => '2023-05-10 09:25:00',
            ],
            [
                'name' => 'OBJETO DE CONTRATO PARA TRANSPORTE DE TURISTAS',
                'description' => 'PRESTAR EL SERVICIO DE TRANSPORTE ESPECIAL DE TURISTAS, DE ACUERDO CONFORMIDAD CON EL NUMERAL 4° ART. 7° DEL DECRETO 478 DEL 2021, CONTRATADOS POR EL CONTRATANTE',
                'created_at' => '2023-05-10 09:24:57',
            ],
        ];

        foreach ($objects as $obj) {
            DB::table('objects_contracts')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => $obj['name'],
                'description' => $obj['description'],
                'created_at' => $obj['created_at'],
                'updated_at' => now(),
            ]);
        }
    }
}
