<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documentTypes = [
            // Personas Naturales
            [
                'code' => '3',
                'prefix' => 'CC',
                'name' => 'Cédula de ciudadanía',
            ],
            [
                'code' => '5',
                'prefix' => 'CE',
                'name' => 'Cédula de extranjería',
            ],
            [
                'code' => '2',
                'prefix' => 'TI',
                'name' => 'Tarjeta de identidad',
            ],
            [
                'code' => '9',
                'prefix' => 'PEP',
                'name' => 'Permiso especial de permanencia',
            ],
            [
                'code' => '8',
                'prefix' => 'DIE',
                'name' => 'Documento de identificación extranjero',
            ],
            [
                'code' => '7',
                'prefix' => 'PA',
                'name' => 'Pasaporte',
            ],
            [
                'code' => '42',
                'prefix' => 'DNI',
                'name' => 'Documento nacional de identidad',
            ],
            // Personas Jurídicas
            [
                'code' => '6',
                'prefix' => 'NIT',
                'name' => 'Número de identificación tributaria',
            ],
            [
                'code' => '41',
                'prefix' => 'RUT',
                'name' => 'Registro único tributario',
            ],
            [
                'code' => '5',
                'prefix' => 'EXT',
                'name' => 'Extranjería',
            ],
            // Otros documentos
            [
                'code' => '1',
                'prefix' => 'RC',
                'name' => 'Registro civil',
            ],
            [
                'code' => '11',
                'prefix' => 'NUIP',
                'name' => 'Número único de identificación personal',
            ],
        ];

        foreach ($documentTypes as $documentType) {
            DB::table('type_of_documents')->updateOrInsert(
                ['prefix' => $documentType['prefix']], // Condición de búsqueda
                [
                    'uuid' => Str::uuid(),
                    'prefix' => $documentType['prefix'],
                    'name' => $documentType['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ Tipos de documentos de identidad insertados/actualizados correctamente.');
    }
}
