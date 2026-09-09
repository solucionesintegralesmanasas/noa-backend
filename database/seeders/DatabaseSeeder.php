<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UsuariosSeeder::class,
            DepartmentsSeeder::class,
            CitiesSeeder::class,
            DocumentTypeSeeder::class,
            TaxRegimesSeeder::class,
            VehicleClassSeeder::class,
            BrandSeeder::class,
            PucCommercialSeeder::class,
            ObjectContractSeeder::class,
            // Nuevos seeders
            BillingResolutionTypeSeeder::class,
            DianParameterSeeder::class,
            InspectionItemSeeder::class,
            MeasurementUnitSeeder::class,
            PaymentMethodSeeder::class,
            TaxResponsibilitySeeder::class,
            TaxTypeSeeder::class,
            TributeSeeder::class,
            WithholdingSeeder::class,
            ContextualQuestionSeeder::class,
            DemoCompanySeeder::class,
        ]);

    }
}
