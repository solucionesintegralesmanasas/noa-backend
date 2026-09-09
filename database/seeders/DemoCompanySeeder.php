<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando seeder de empresa demo...');

        $catalog = $this->loadCatalog();

        [$companyUuid, $user] = $this->createCompany($catalog);

        $this->command->info('Empresa demo creada: Transportes Rápidos del Norte S.A.S.');

        $branchUuids = $this->createBranches($companyUuid, $catalog);

        $this->command->info('3 sedes creadas correctamente.');

        [$affiliatesUuids, $driversUuids, $companyThirdPartyUuid] = $this->createThirdParties($companyUuid, $catalog);

        $this->command->info('10 afiliados y 15 conductores creados.');

        $this->linkUserToCompany($user, $companyUuid);

        $driverLicenseUuids = $this->createDriverLicenses($companyUuid, $driversUuids, $catalog);

        $this->command->info('15 licencias de conducción creadas.');

        $vehicleUuids = $this->createVehicles($companyUuid, $branchUuids, $affiliatesUuids, $companyThirdPartyUuid, $catalog);

        $this->command->info('10 vehículos creados correctamente.');

        $this->createOwners($companyUuid, $vehicleUuids, $affiliatesUuids, $companyThirdPartyUuid, $catalog);

        $this->createVehicleDocuments($companyUuid, $vehicleUuids);

        $this->command->info('Documentación de vehículos (SOAT, RCC, RCE, RTM) creada correctamente.');

        $this->createOperationCards($companyUuid, $vehicleUuids, $affiliatesUuids);

        $this->command->info('10 tarjetas de operación creadas.');

        $this->createVehicleBranches($companyUuid, $vehicleUuids, $branchUuids);

        $enablingResolutionUuid = $this->createEnablingResolution($companyUuid);

        $this->command->info('Resolución de habilitación creada.');

        $this->createConveyorCapacity($enablingResolutionUuid);

        $this->createBusinessCollaborationAgreements($companyUuid, $vehicleUuids);

        $this->command->info('3 convenios empresariales creados.');

        $this->createMaintenanceRecords($companyUuid, $vehicleUuids, $driversUuids);

        $this->command->info('Mantenimientos preventivos creados.');

        $this->command->info('¡Empresa demo creada exitosamente!');
    }

    private function loadCatalog(): object
    {
        $catalog = new \stdClass();

        $catalog->documentTypes = DB::table('type_of_documents')->get()->keyBy('prefix');
        $catalog->cities = DB::table('cities')->get()->keyBy('dane_code');
        $catalog->brands = DB::table('brands')->get()->keyBy('description');
        $catalog->vehicleClasses = DB::table('vehicle_class')->get()->keyBy('class_code_class');
        $catalog->taxRegimes = DB::table('tax_regimes')->get()->keyBy('code');
        $catalog->taxResponsibilities = DB::table('tax_responsibilities')->get()->keyBy('code');

        return $catalog;
    }

    private function createCompany(object $catalog): array
    {
        $companyUuid = (string) Str::uuid();
        $nit = $catalog->documentTypes->get('NIT');

        DB::table('companies')->insert([
            'uuid' => $companyUuid,
            'person_type' => 'PERSONA JURIDICA',
            'type_of_company' => 'PRIVADO',
            'economic_sector' => 'TRANSPORTE',
            'legal_structure' => 'SOCIEDAD POR ACCIONES SIMPLIFICADA - SAS',
            'document_type_uuid' => $nit->uuid,
            'document_number' => '901234567',
            'verification_digit' => '8',
            'business_name' => 'TRANSPORTES RÁPIDOS DEL NORTE S.A.S.',
            'trade_name' => 'TRANS RÁPIDOS',
            'commercial_registration' => '1234567',
            'municipality_uuid' => $catalog->cities->get('11001')->uuid,
            'address' => 'Cra 30 # 45-20',
            'phone' => '6012345678',
            'email' => 'contacto@transportesrapidos.com',
            'tax_regime_uuid' => $catalog->taxRegimes->get('48')->uuid,
            'currency_code' => 'COP',
            'approximate_number_of_employees' => 25,
            'web_page' => 'https://transportesrapidos.com',
            'country_code' => 'CO',
            'legal_representative_name' => 'Carlos Andrés',
            'legal_representative_last_name' => 'Mendoza López',
            'legal_representative_document_type' => 'CC',
            'legal_representative_document_number' => '80123456',
            'legal_representative_nationality' => 'COLOMBIANA',
            'legal_representative_document_issue_date' => '2010-05-15',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::firstOrCreate(
            ['email' => 'demo@transportesrapidos.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin Demo',
                'user_name' => 'admin_demo',
                'password' => bcrypt('demo123456'),
                'email_verified_at' => now(),
                'status' => 1,
            ]
        );
        $user->assignRole('ADMIN_EMPRESA');

        return [$companyUuid, $user];
    }

    private function linkUserToCompany(User $user, string $companyUuid): void
    {
        DB::table('company_user')->insertOrIgnore([
            'user_id' => $user->id,
            'company_uuid' => $companyUuid,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBranches(string $companyUuid, object $catalog): array
    {
        $branches = [
            ['name' => 'SEDE PRINCIPAL', 'address' => 'Cra 30 # 45-20', 'city' => '11001', 'is_primary' => true],
            ['name' => 'SEDE NORTE', 'address' => 'Calle 50 # 20-15', 'city' => '08001', 'is_primary' => false],
            ['name' => 'SEDE OCCIDENTE', 'address' => 'Av 3N # 10-50', 'city' => '76001', 'is_primary' => false],
        ];

        $branchUuids = [];
        foreach ($branches as $branch) {
            $uuid = (string) Str::uuid();
            $branchUuids[] = $uuid;
            DB::table('branches')->insert([
                'uuid' => $uuid,
                'company_uuid' => $companyUuid,
                'name' => $branch['name'],
                'address' => $branch['address'],
                'municipality_uuid' => $catalog->cities->get($branch['city'])->uuid,
                'is_primary' => $branch['is_primary'],
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $branchUuids;
    }

    private function createThirdParties(string $companyUuid, object $catalog): array
    {
        $cc = $catalog->documentTypes->get('CC');
        $nit = $catalog->documentTypes->get('NIT');
        $bogota = $catalog->cities->get('11001')->uuid;
        $taxResponsibility = $catalog->taxResponsibilities->get('R-99-PN');
        $taxRespO15 = $catalog->taxResponsibilities->get('O-15');

        $affiliates = [
            ['type' => 'JURIDICA', 'company_name' => 'TRANSPORTES GARCÍA Y ASOCIADOS S.A.S.', 'trade_name' => 'TRANS GARCÍA', 'document' => '901111111', 'dv' => '2'],
            ['type' => 'JURIDICA', 'company_name' => 'LOGÍSTICA DEL VALLE S.A.S.', 'trade_name' => 'LOGIVALLE', 'document' => '901222222', 'dv' => '5'],
            ['type' => 'JURIDICA', 'company_name' => 'FLOTA RÁPIDA DEL NORTE S.A.S.', 'trade_name' => 'FLOTA NORTE', 'document' => '901333333', 'dv' => '8'],
            ['type' => 'NATURAL', 'first_name' => 'Luis Alberto', 'last_name' => 'García Rivera', 'document' => '10234567'],
            ['type' => 'NATURAL', 'first_name' => 'María Fernanda', 'last_name' => 'Rodríguez Pérez', 'document' => '10234568'],
            ['type' => 'NATURAL', 'first_name' => 'Jorge Eliécer', 'last_name' => 'Martínez Torres', 'document' => '10234569'],
            ['type' => 'NATURAL', 'first_name' => 'Ana Milena', 'last_name' => 'Sánchez Gómez', 'document' => '10234570'],
            ['type' => 'NATURAL', 'first_name' => 'Pedro Antonio', 'last_name' => 'López Castillo', 'document' => '10234571'],
            ['type' => 'NATURAL', 'first_name' => 'Carmen Elena', 'last_name' => 'Díaz Moreno', 'document' => '10234572'],
            ['type' => 'NATURAL', 'first_name' => 'Fernando José', 'last_name' => 'Ortiz Ramírez', 'document' => '10234573'],
        ];

        $drivers = [
            ['first_name' => 'Jairo Andrés', 'last_name' => 'Patiño Suárez', 'document' => '11234501'],
            ['first_name' => 'Rosa María', 'last_name' => 'Álvarez Pardo', 'document' => '11234502'],
            ['first_name' => 'Diego Armando', 'last_name' => 'Valencia Ríos', 'document' => '11234503'],
            ['first_name' => 'Lucía Inés', 'last_name' => 'Cárdenas Mora', 'document' => '11234504'],
            ['first_name' => 'Ricardo Alonso', 'last_name' => 'Peña Beltrán', 'document' => '11234505'],
            ['first_name' => 'William Alexander', 'last_name' => 'Gutiérrez Pérez', 'document' => '11234506'],
            ['first_name' => 'Natalia Andrea', 'last_name' => 'Moreno Castillo', 'document' => '11234507'],
            ['first_name' => 'Óscar David', 'last_name' => 'Rincón Suárez', 'document' => '11234508'],
            ['first_name' => 'Julián Esteban', 'last_name' => 'Cifuentes Rojas', 'document' => '11234509'],
            ['first_name' => 'Adriana Marcela', 'last_name' => 'Herrera Vargas', 'document' => '11234510'],
            ['first_name' => 'Cristian Camilo', 'last_name' => 'Parra Gómez', 'document' => '11234511'],
            ['first_name' => 'Paola Andrea', 'last_name' => 'Jiménez Quintero', 'document' => '11234512'],
            ['first_name' => 'Mauricio Alberto', 'last_name' => 'Vanegas Londoño', 'document' => '11234513'],
            ['first_name' => 'Liliana Patricia', 'last_name' => 'Castaño Orozco', 'document' => '11234514'],
            ['first_name' => 'Sergio Alejandro', 'last_name' => 'Mora Beltrán', 'document' => '11234515'],
        ];

        $affiliatesUuids = [];
        $driversUuids = [];

        foreach ($affiliates as $affiliate) {
            $uuid = (string) Str::uuid();
            $affiliatesUuids[] = $uuid;

            $isJuridica = $affiliate['type'] === 'JURIDICA';

            $data = [
                'uuid' => $uuid,
                'company_uuid' => $companyUuid,
                'person_type' => $affiliate['type'],
                'document_type_uuid' => $isJuridica ? $nit->uuid : $cc->uuid,
                'document_number' => $affiliate['document'],
                'nit_check_digit' => $isJuridica ? $affiliate['dv'] : null,
                'company_name' => $isJuridica ? $affiliate['company_name'] : null,
                'trade_name' => $isJuridica ? $affiliate['trade_name'] : null,
                'first_name' => $isJuridica ? null : $affiliate['first_name'],
                'last_name' => $isJuridica ? null : $affiliate['last_name'],
                'email' => $isJuridica
                    ? $this->sanitizeEmail(str_replace(' ', '_', $affiliate['company_name']) . '@email.com')
                    : $this->sanitizeEmail(str_replace(' ', '.', $affiliate['first_name'] . '.' . $affiliate['last_name']) . '@email.com'),
                'phone' => '300' . rand(1000000, 9999999),
                'address' => 'Calle ' . rand(1, 100) . ' # ' . rand(1, 50) . '-' . rand(1, 99),
                'municipality_uuid' => $bogota,
                'tax_regime' => $isJuridica ? '48' : '49',
                'tax_responsibility_uuid' => $isJuridica ? $taxRespO15->uuid : $taxResponsibility->uuid,
                'is_customer' => false,
                'is_supplier' => false,
                'is_employee' => false,
                'is_affiliate' => true,
                'is_driver' => false,
                'is_others' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('third_parties')->insert($data);
        }

        foreach ($drivers as $driver) {
            $uuid = (string) Str::uuid();
            $driversUuids[] = $uuid;
            DB::table('third_parties')->insert([
                'uuid' => $uuid,
                'company_uuid' => $companyUuid,
                'person_type' => 'NATURAL',
                'document_type_uuid' => $cc->uuid,
                'document_number' => $driver['document'],
                'first_name' => $driver['first_name'],
                'last_name' => $driver['last_name'],
                'email' => $this->sanitizeEmail(str_replace(' ', '.', $driver['first_name'] . '.' . $driver['last_name']) . '@email.com'),
                'phone' => '300' . rand(1000000, 9999999),
                'address' => 'Calle ' . rand(1, 100) . ' # ' . rand(1, 50) . '-' . rand(1, 99),
                'municipality_uuid' => $bogota,
                'tax_regime' => '49',
                'tax_responsibility_uuid' => $taxResponsibility->uuid,
                'is_customer' => false,
                'is_supplier' => false,
                'is_employee' => false,
                'is_affiliate' => false,
                'is_driver' => true,
                'is_others' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $companyThirdPartyUuid = (string) Str::uuid();
        DB::table('third_parties')->insert([
            'uuid' => $companyThirdPartyUuid,
            'company_uuid' => $companyUuid,
            'person_type' => 'JURIDICA',
            'document_type_uuid' => $nit->uuid,
            'document_number' => '901234567',
            'nit_check_digit' => '8',
            'company_name' => 'TRANSPORTES RÁPIDOS DEL NORTE S.A.S.',
            'trade_name' => 'TRANS RÁPIDOS',
            'first_name' => null,
            'last_name' => null,
            'email' => 'contacto@transportesrapidos.com',
            'phone' => '6012345678',
            'address' => 'Cra 30 # 45-20',
            'municipality_uuid' => $bogota,
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $taxRespO15->uuid,
            'is_customer' => false,
            'is_supplier' => false,
            'is_employee' => false,
            'is_affiliate' => false,
            'is_driver' => false,
            'is_others' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$affiliatesUuids, $driversUuids, $companyThirdPartyUuid];
    }

    private function createDriverLicenses(string $companyUuid, array $driverUuids, object $catalog): array
    {
        $categories = ['C1', 'C2', 'C3'];
        $licenseUuids = [];

        foreach ($driverUuids as $index => $thirdPartyUuid) {
            $uuid = (string) Str::uuid();
            $licenseUuids[] = $uuid;
            $category = $categories[$index % count($categories)];
            $year = rand(2018, 2022);

            DB::table('driver_licenses')->insert([
                'uuid' => $uuid,
                'company_uuid' => $companyUuid,
                'third_party_uuid' => $thirdPartyUuid,
                'number' => 'LIC-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'category' => $category,
                'issue_date' => "$year-01-15",
                'expiration_date' => ($year + 10) . '-01-15',
                'restrictions' => 'NINGUNA',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $licenseUuids;
    }

    private function createVehicles(string $companyUuid, array $branchUuids, array $affiliatesUuids, string $companyThirdPartyUuid, object $catalog): array
    {
        $brands = $catalog->brands;
        $classes = $catalog->vehicleClasses;
        $buseta = $classes->get('3');
        $microbus = $classes->get('7');
        $camioneta = $classes->get('5');
        $automovil = $classes->get('1');

        $juridicaUuids = array_slice($affiliatesUuids, 0, 3);
        $naturalUuids = array_slice($affiliatesUuids, 3);

        $vehicles = [
            ['plate' => 'ABC123', 'brand' => 'TOYOTA', 'class' => $buseta, 'line' => 'HIACE', 'model' => 2022, 'color' => 'BLANCO', 'capacity' => 19, 'engine' => '2KD-FTV'],
            ['plate' => 'DEF456', 'brand' => 'CHEVROLET', 'class' => $camioneta, 'line' => 'NHR', 'model' => 2021, 'color' => 'BLANCO', 'capacity' => 15, 'engine' => '4JB1'],
            ['plate' => 'GHI789', 'brand' => 'RENAULT', 'class' => $microbus, 'line' => 'MASTER', 'model' => 2023, 'color' => 'GRIS', 'capacity' => 17, 'engine' => 'M9R'],
            ['plate' => 'JKL012', 'brand' => 'MERCEDES BENZ', 'class' => $buseta, 'line' => 'SPRINTER', 'model' => 2022, 'color' => 'BLANCO', 'capacity' => 21, 'engine' => 'OM651'],
            ['plate' => 'MNO345', 'brand' => 'NISSAN', 'class' => $camioneta, 'line' => 'FRONTIER', 'model' => 2020, 'color' => 'ROJO', 'capacity' => 5, 'engine' => 'YD25'],
            ['plate' => 'PQR678', 'brand' => 'HYUNDAI', 'class' => $automovil, 'line' => 'ELANTRA', 'model' => 2023, 'color' => 'NEGRO', 'capacity' => 4, 'engine' => 'G4NA'],
            ['plate' => 'STU901', 'brand' => 'JAC', 'class' => $camioneta, 'line' => 'SUNRAY', 'model' => 2021, 'color' => 'PLATA', 'capacity' => 14, 'engine' => 'HFC4DA1'],
            ['plate' => 'VWX234', 'brand' => 'KIA', 'class' => $automovil, 'line' => 'RIO', 'model' => 2022, 'color' => 'AZUL', 'capacity' => 4, 'engine' => 'G4FG'],
            ['plate' => 'YZA567', 'brand' => 'DFSK', 'class' => $microbus, 'line' => 'C37', 'model' => 2023, 'color' => 'BLANCO', 'capacity' => 16, 'engine' => 'DK15'],
            ['plate' => 'BCD890', 'brand' => 'FORD', 'class' => $camioneta, 'line' => 'RANGER', 'model' => 2020, 'color' => 'GRIS', 'capacity' => 5, 'engine' => 'Puma 2.2'],
        ];

        $vehicleUuids = [];
        foreach ($vehicles as $index => $vehicle) {
            $uuid = (string) Str::uuid();
            $vehicleUuids[] = $uuid;
            $brand = $brands->get($vehicle['brand']);

            if ($index < 3) {
                $ownerUuid = $juridicaUuids[$index];
            } else {
                $ownerUuid = $naturalUuids[$index - 3];
            }

            DB::table('vehicles')->insert([
                'uuid' => $uuid,
                'company_uuid' => $companyUuid,
                'third_party_uuid' => $ownerUuid,
                'vehicle_license_plate' => $vehicle['plate'],
                'transit_license_number' => 'TLN-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'type_of_service' => 'PUBLICO',
                'vehicle_class_uuid' => $vehicle['class']->uuid,
                'brand_uuid' => $brand->uuid,
                'line' => $vehicle['line'],
                'model' => $vehicle['model'],
                'color' => $vehicle['color'],
                'serial_number' => 'SER' . strtoupper(Str::random(10)),
                'engine_number' => $vehicle['engine'] . '-' . strtoupper(Str::random(6)),
                'chassis_number' => 'CHS' . strtoupper(Str::random(12)),
                'vin_number' => 'VIN' . strtoupper(Str::random(14)),
                'engine_displacement' => rand(1800, 3000),
                'body_type' => 'CARRY',
                'fuel_type' => 'DIESEL',
                'registration_date' => (2020 + rand(0, 3)) . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-15',
                'transit_authority' => 'SECRETARÍA DE MOVILIDAD',
                'doors' => 4,
                'load_capacity' => 500,
                'gross_vehicle_weight' => 3500,
                'passenger_capacity' => $vehicle['capacity'],
                'seated_passenger_capacity' => $vehicle['capacity'],
                'number_of_axles' => 2,
                'exact_payment' => false,
                'internal_number' => 'INT-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'address_type' => 'RURAL',
                'steering_type' => 'HIDRAULICA',
                'transmission_type' => 'MANUAL',
                'number_of_speeds' => 5,
                'bearing_type' => 'RODAMIENTOS',
                'rear_suspension' => 'BALLESTA',
                'number_of_tires' => 4,
                'rim_size' => 15,
                'rim_material' => 'ALEACION',
                'front_brake_type' => 'DISCO',
                'rear_brake_type' => 'TAMBOR',
                'number_of_windows' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $vehicleUuids;
    }

    private function createOwners(string $companyUuid, array $vehicleUuids, array $affiliatesUuids, string $companyThirdPartyUuid, object $catalog): void
    {
        $cc = $catalog->documentTypes->get('CC');
        $nit = $catalog->documentTypes->get('NIT');
        $juridicaUuids = array_slice($affiliatesUuids, 0, 3);
        $naturalUuids = array_slice($affiliatesUuids, 3);

        foreach ($vehicleUuids as $index => $vehicleUuid) {
            $ownerUuid = $index < 3 ? $juridicaUuids[$index] : $naturalUuids[$index - 3];

            $thirdParty = DB::table('third_parties')
                ->where('uuid', $ownerUuid)
                ->first();

            $isJuridica = $thirdParty->person_type === 'JURIDICA';

            DB::table('owners')->insert([
                'uuid' => (string) Str::uuid(),
                'third_party_uuid' => $ownerUuid,
                'vehicle_uuid' => $vehicleUuid,
                'document_type_uuid' => $isJuridica ? $nit->uuid : $cc->uuid,
                'owner_name' => $isJuridica ? $thirdParty->company_name : ($thirdParty->first_name . ' ' . $thirdParty->last_name),
                'document_number' => $thirdParty->document_number,
                'verification_digit' => $isJuridica ? $thirdParty->nit_check_digit : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createVehicleDocuments(string $companyUuid, array $vehicleUuids): void
    {
        $docs = [
            [
                'type' => 'SOAT', 'entity' => 'SEGUROS DEL ESTADO', 'policy_prefix' => 'SOAT',
                'expiry' => '2027-12-31',
            ],
            [
                'type' => 'RCC', 'entity' => 'SURA', 'policy_prefix' => 'RCC',
                'expiry' => now()->addMonths(11)->format('Y-m-d'),
            ],
            [
                'type' => 'RCE', 'entity' => 'SURA', 'policy_prefix' => 'RCE',
                'expiry' => now()->addMonths(11)->format('Y-m-d'),
            ],
            [
                'type' => 'RTM', 'entity' => 'CENAF', 'policy_prefix' => 'RTM',
                'expiry' => now()->addMonths(11)->format('Y-m-d'),
            ],
        ];

        foreach ($vehicleUuids as $vehicleUuid) {
            foreach ($docs as $doc) {
                $issueDate = \Carbon\Carbon::parse($doc['expiry'])->subYear()->format('Y-m-d');

                DB::table('vehicle_documents')->insert([
                    'uuid' => (string) Str::uuid(),
                    'company_uuid' => $companyUuid,
                    'vehicle_uuid' => $vehicleUuid,
                    'document_type' => $doc['type'],
                    'policy_number' => $doc['policy_prefix'] . '-' . strtoupper(Str::random(8)),
                    'issue_date' => $issueDate,
                    'effective_date' => $issueDate,
                    'expiry_date' => $doc['expiry'],
                    'issuing_entity' => $doc['entity'],
                    'status' => 'SI',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function createOperationCards(string $companyUuid, array $vehicleUuids, array $affiliatesUuids): void
    {
        $juridicaNames = [
            'TRANSPORTES GARCÍA Y ASOCIADOS S.A.S.',
            'LOGÍSTICA DEL VALLE S.A.S.',
            'FLOTA RÁPIDA DEL NORTE S.A.S.',
        ];

        foreach ($vehicleUuids as $index => $vehicleUuid) {
            $issueDate = now()->subMonths(rand(1, 6))->format('Y-m-d');
            $expirationDate = now()->addMonths(rand(6, 18))->format('Y-m-d');
            $affiliatedCompany = $index < 3
                ? $juridicaNames[$index]
                : 'TRANSPORTES RÁPIDOS DEL NORTE S.A.S.';

            DB::table('operation_cards')->insert([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $companyUuid,
                'vehicle_uuid' => $vehicleUuid,
                'affiliated_company' => $affiliatedCompany,
                'area_of_coverage' => 'NACIONAL',
                'service_type' => 'TRANSPORTE ESPECIAL',
                'transport_mode' => 'CARRETERA',
                'issue_date' => $issueDate,
                'expiration_date' => $expirationDate,
                'operating_card_number' => 'TO-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createVehicleBranches(string $companyUuid, array $vehicleUuids, array $branchUuids): void
    {
        foreach ($vehicleUuids as $index => $vehicleUuid) {
            $branchIndex = $index % count($branchUuids);

            DB::table('vehicles_branches')->insert([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $companyUuid,
                'vehicle_uuid' => $vehicleUuid,
                'branch_uuid' => $branchUuids[$branchIndex],
                'entry_date' => '2024-01-01',
                'exit_date' => '9999-12-31',
                'exit_type' => 'ENTRADA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createEnablingResolution(string $companyUuid): string
    {
        $uuid = (string) Str::uuid();

        DB::table('enabling_resolutions')->insert([
            'uuid' => $uuid,
            'company_uuid' => $companyUuid,
            'resolution_number' => 'RES-HAB-2024-001',
            'number_fuec' => 'FUEC-' . strtoupper(Str::random(8)),
            'territorial_code' => 'CUN-01',
            'resolution_date' => '2024-01-30',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $uuid;
    }

    private function createConveyorCapacity(string $enablingResolutionUuid): void
    {
        DB::table('conveyor_capacity')->insert([
            'uuid' => (string) Str::uuid(),
            'enabling_resolution_uuid' => $enablingResolutionUuid,
            'vehicle_type' => 'BUS',
            'authorized_capacity' => 30,
            'current_capacity' => 10,
            'minimum_own_capacity' => 5,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBusinessCollaborationAgreements(string $companyUuid, array $vehicleUuids): void
    {
        $agreements = [
            ['entity_nit' => '8909001234', 'entity_name' => 'EMPRESA DE ACUEDUCTO DE BOGOTÁ S.A. E.S.P.'],
            ['entity_nit' => '8600024678', 'entity_name' => 'CODENSA S.A. E.S.P.'],
            ['entity_nit' => '8999990345', 'entity_name' => 'SECRETARÍA DE EDUCACIÓN DISTRITAL'],
        ];

        foreach ($agreements as $index => $agreement) {
            DB::table('business_collaboration_agreements')->insert([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $companyUuid,
                'vehicle_uuid' => $vehicleUuids[$index],
                'resolution_number' => 'RES-CONV-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'agreement_internal_id' => 'CONV-INT-' . strtoupper(Str::random(8)),
                'contracting_entity_nit' => $agreement['entity_nit'],
                'contracting_entity_name' => $agreement['entity_name'],
                'effective_date' => '2025-01-01',
                'expiry_date' => '2027-12-31',
                'rep_name' => 'Representante Legal',
                'rep_document_id' => 'CC-80123456',
                'transport_modality' => 'PASAJEROS',
                'max_fleet_capacity' => 10,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function sanitizeEmail(string $value): string
    {
        return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $value));
    }

    private function createMaintenanceRecords(string $companyUuid, array $vehicleUuids, array $driversUuids): void
    {
        $descriptions = [
            'Mantenimiento preventivo de 10.000 Km: Cambio de aceite de motor, filtro de aire y filtro de combustible.',
            'Inspección periódica del sistema de frenos, rotación de llantas, alineación y balanceo.',
            'Cambio de pastillas de freno delanteras, bandas traseras y rectificación de discos.',
            'Revisión y ajuste del sistema de suspensión, bujes, amortiguadores y terminales de dirección.',
            'Mantenimiento del sistema eléctrico, diagnóstico por escáner y verificación de batería.',
            'Sustitución de líquido de frenos, refrigerante de motor y correas de accesorios.',
        ];

        // Obtener conductores reales para asignar a los mantenimientos
        $drivers = DB::table('third_parties')
            ->whereIn('uuid', $driversUuids)
            ->get()
            ->values();

        foreach ($vehicleUuids as $index => $vehicleUuid) {
            if ($index >= 6) {
                break;
            }

            $driver = $drivers->get($index % $drivers->count());
            $driverName = $driver ? ($driver->first_name . ' ' . $driver->last_name) : 'Jairo Patiño';

            DB::table('maintenance')->insert([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $companyUuid,
                'vehicle_uuid' => $vehicleUuid,
                'maintenance_type' => 'PREVENTIVA',
                'mileage' => (12 + $index * 5) * 1000,
                'service_description' => $descriptions[$index % count($descriptions)],
                'mechanic_name' => $driverName,
                'workshop_name' => 'Taller Central de Mantenimiento',
                'maintenance_date' => now()->subDays($index * 2 + 1)->format('Y-m-d'),
                'labor_cost' => 180000.00,
                'parts_cost' => 320000.00,
                'invoice_number' => 'FAC-' . str_pad($index + 101, 5, '0', STR_PAD_LEFT),
                'next_maintenance_date' => now()->addMonths(3)->format('Y-m-d'),
                'notes' => 'Mantenimiento preventivo periódico ejecutado satisfactoriamente por el conductor.',
                'status' => 'Finalizado',
                'created_at' => now()->subDays($index * 2 + 1),
                'updated_at' => now()->subDays($index * 2 + 1),
            ]);
        }
    }
}
