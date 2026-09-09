<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // CREACION DE ROLES
        $superAdmin = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'api']);
        $adminEmpresa = Role::firstOrCreate(['name' => 'ADMIN_EMPRESA', 'guard_name' => 'api']);
        $empleado = Role::firstOrCreate(['name' => 'EMPLEADO', 'guard_name' => 'api']);
        $afiliado = Role::firstOrCreate(['name' => 'AFILIADO', 'guard_name' => 'api']);
        $conductor = Role::firstOrCreate(['name' => 'CONDUCTOR', 'guard_name' => 'api']);

        $adminEmpresaPerms = Permission::query()->whereIn('name', [
            'companies.update',
            'companies.profile',

            'contacts.index',
            'contacts.create',
            'contacts.update',
            'contacts.delete',

            'branches.index',
            'branches.create',
            'branches.update',
            'branches.delete',
            'branches.profile',

            'economic_activities.index',
            'economic_activities.create',
            'economic_activities.update',
            'economic_activities.delete',

            'bank_details.index',
            'bank_details.create',
            'bank_details.update',
            'bank_details.delete',

            'tax_information.index',
            'tax_information.create',
            'tax_information.update',
            'tax_information.delete',

            'enabling_resolutions.index',
            'enabling_resolutions.create',
            'enabling_resolutions.update',
            'enabling_resolutions.delete',

            'conveyor_capacity.index',
            'conveyor_capacity.create',
            'conveyor_capacity.update',
            'conveyor_capacity.delete',

            'experiences.index',
            'experiences.create',
            'experiences.update',
            'experiences.delete',

            'rup_records.index',
            'rup_records.create',
            'rup_records.update',
            'rup_records.delete',

            'financial_statements.index',
            'financial_statements.create',
            'financial_statements.update',
            'financial_statements.delete',
            'financial_statements.profile',

            'tax_declarations.index',
            'tax_declarations.create',
            'tax_declarations.update',
            'tax_declarations.delete',
            'tax_declarations.profile',

            'occupational_safety_records.index',
            'occupational_safety_records.create',
            'occupational_safety_records.update',
            'occupational_safety_records.delete',
            'occupational_safety_records.profile',

            'customer.index',
            'customer.create',
            'customer.update',
            'customer.delete',
            'customer.profile',

            'supplier.index',
            'supplier.create',
            'supplier.update',
            'supplier.delete',
            'supplier.profile',

            'employee.index',
            'employee.create',
            'employee.update',
            'employee.delete',
            'employee.profile',

            'employmentContracts.index',
            'employmentContracts.create',
            'employmentContracts.update',
            'employmentContracts.delete',
            'employmentContracts.show',


            'affiliate.index',
            'affiliate.create',
            'affiliate.update',
            'affiliate.delete',
            'affiliate.profile',

            'driver.index',
            'driver.create',
            'driver.update',
            'driver.delete',
            'driver.profile',

            'third_parties.index',
            'third_parties.create',
            'third_parties.update',
            'third_parties.delete',
            'third_parties.profile',

            'categories.index',
            'categories.create',
            'categories.update',
            'categories.delete',

            'warehouses.index',
            'warehouses.create',
            'warehouses.update',
            'warehouses.delete',

            'products.index',
            'products.create',
            'products.update',
            'products.delete',

            'product_variants.index',
            'product_variants.create',
            'product_variants.update',
            'product_variants.delete',

            'chart_of_accounts.index',
            'chart_of_accounts.create',
            'chart_of_accounts.update',
            'chart_of_accounts.delete',

            'cost_centers.index',
            'cost_centers.create',
            'cost_centers.update',
            'cost_centers.delete',

            'voucher_types.index',
            'voucher_types.create',
            'voucher_types.update',
            'voucher_types.delete',

            'bank_accounts.index',
            'bank_accounts.create',
            'bank_accounts.update',
            'bank_accounts.delete',

            'budgets.index',
            'budgets.create',
            'budgets.update',
            'budgets.delete',

            'billing_providers.index',
            'billing_providers.create',
            'billing_providers.update',
            'billing_providers.delete',

            'billing_resolutions.index',
            'billing_resolutions.create',
            'billing_resolutions.update',
            'billing_resolutions.delete',

            'fiscal_documents.index',
            'fiscal_documents.create',
            'fiscal_documents.update',
            'fiscal_documents.delete',

            'commercial_invoices.index',
            'commercial_invoices.create',
            'commercial_invoices.update',
            'commercial_invoices.delete',

            'electronic_invoices.index',
            'electronic_invoices.create',
            'electronic_invoices.update',
            'electronic_invoices.delete',

            'credit_notes.index',
            'credit_notes.create',
            'credit_notes.update',
            'credit_notes.delete',

            'debit_notes.index',
            'debit_notes.create',
            'debit_notes.update',
            'debit_notes.delete',

            'purchase_orders.index',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.delete',

            'purchases.index',
            'purchases.create',
            'purchases.update',
            'purchases.delete',

            'support_documents.index',
            'support_documents.create',
            'support_documents.update',
            'support_documents.delete',

            'adjustment_notes.index',
            'adjustment_notes.create',
            'adjustment_notes.update',
            'adjustment_notes.delete',

            'tax_periods.index',
            'tax_periods.create',
            'tax_periods.update',
            'tax_periods.delete',

            'electronic_documents.index',
            'electronic_documents.create',
            'electronic_documents.update',
            'electronic_documents.delete',

            'affiliate_admin_charges.index',
            'affiliate_admin_charges.create',
            'affiliate_admin_charges.update',
            'affiliate_admin_charges.delete',
            'affiliate_admin_charges.profile',

            'extract_of_contract.index',
            'extract_of_contract.create',
            'extract_of_contract.update',
            'extract_of_contract.delete',

            'vehicles.index',
            'vehicles.create',
            'vehicles.update',
            'vehicles.delete',
            'vehicles.profile',
            'vehicles.change_branch',
            'vehicles.history_pdf',
            'vehicles.technical_sheet_pdf',

            'vehicle_documents.index',
            'vehicle_documents.create',
            'vehicle_documents.update',
            'vehicle_documents.delete',

            'operation_cards.index',
            'operation_cards.create',
            'operation_cards.update',
            'operation_cards.delete',

            'business_collaboration_agreements.index',
            'business_collaboration_agreements.create',
            'business_collaboration_agreements.update',
            'business_collaboration_agreements.delete',

            'maintenance.index',
            'maintenance.create',
            'maintenance.update',
            'maintenance.delete',
            'maintenance.forecast',

            'maintenance_parts.index',
            'maintenance_parts.create',
            'maintenance_parts.update',
            'maintenance_parts.delete',

            'vehicle_inspections.index',
            'vehicle_inspections.create',
            'vehicle_inspections.update',
            'vehicle_inspections.delete',

            'inspection_results.index',
            'inspection_results.create',
            'inspection_results.update',
            'inspection_results.delete',

            'driver_licenses.index',
            'driver_licenses.create',
            'driver_licenses.update',
            'driver_licenses.delete',

            'security_contributions.index',
            'security_contributions.create',
            'security_contributions.update',
            'security_contributions.delete',

            'territorial_directors.index',
            'territorial_directors.create',
            'territorial_directors.update',
            'territorial_directors.delete',
            'territorial_directors.toggle-status',

            'procedures.index',
            'procedures.create',
            'procedures.update',
            'procedures.delete',

            'fleet_service_contracts.index',
            'fleet_service_contracts.create',
            'fleet_service_contracts.update',
            'fleet_service_contracts.delete',

            'capacity_inventory.index',
            'capacity_inventory.create',
            'capacity_inventory.update',
            'capacity_inventory.delete',

            'objects_contracts.index',
            'objects_contracts.create',
            'objects_contracts.update',
            'objects_contracts.delete',

            'contractors.index',
            'contractors.create',
            'contractors.update',
            'contractors.delete',

            'fuec.index',
            'fuec.create',
            'fuec.update',
            'fuec.delete',
            'fuec.profile',

            'system_configurations.index',
            'system_configurations.create',
            'system_configurations.update',
            'system_configurations.delete',

            'control_sheets.index',
            'control_sheets.create',
            'control_sheets.update',
            'control_sheets.delete',

            'service_delivery_control_sheets.index',
            'service_delivery_control_sheets.view',
            'service_delivery_control_sheets.create',
            'service_delivery_control_sheets.update',
            'service_delivery_control_sheets.edit',
            'service_delivery_control_sheets.delete',
            'service_delivery_control_sheets.history_pdf',
            'service_delivery_control_sheets.technical_sheet_pdf',
            'service_delivery_control_sheets.cold_chain_pdf',

            'assistant_configuration.index',
            'assistant_configuration.create',
            'assistant_configuration.update',
            'assistant_configuration.delete',

            'projects.index',
            'projects.create',
            'projects.update',
            'projects.delete',
            'projects.profile',
        ], 'and', false)->get();

        $empleadoPerms = Permission::query()->whereIn('name', [''], 'and', false)->get();
        $afiliadoPerms = Permission::query()->whereIn('name', [
            'driver.index',
            'driver.create',
            'driver.update',
            'driver.profile',

            'vehicles.index',
            'vehicles.profile',
            'vehicles.change_branch',
            'vehicles.history_pdf',
            'vehicles.technical_sheet_pdf',

            'vehicle_documents.index',

            'operation_cards.index',

            'business_collaboration_agreements.index',

            'maintenance.index',
            'maintenance.create',
            'maintenance.update',
            'maintenance.forecast',

            'maintenance_parts.index',
            'maintenance_parts.create',
            'maintenance_parts.update',

            'vehicle_inspections.index',
            'vehicle_inspections.create',
            'vehicle_inspections.update',

            'inspection_results.index',
            'inspection_results.create',
            'inspection_results.update',

            'driver_licenses.index',
            'driver_licenses.create',
            'driver_licenses.update',

            'security_contributions.index',
            'security_contributions.create',

            'fuec.index',
            'fuec.create',
            'fuec.profile',

        ], 'and', false)->get();
        $conductorPerms = Permission::query()->whereIn('name', [
            'vehicle_inspections.index',
            'vehicle_inspections.create',

            'fuec.index',
        ], 'and', false)->get();

        // 1. Asignar TODOS los permisos al SUPERADMIN
        $superAdmin->syncPermissions(Permission::all());

        // 2. Sincronizar permisos específicos para el resto
        $adminEmpresa->syncPermissions($adminEmpresaPerms);
        $empleado->syncPermissions($empleadoPerms);
        $afiliado->syncPermissions($afiliadoPerms);
        $conductor->syncPermissions($conductorPerms);

        $this->command->info('✅ Roles creados y permisos asignados correctamente.');

        $this->command->table(
            ['Rol', 'Cantidad de Permisos'],
            [
                ['SUPERADMIN',     $superAdmin->permissions()->count()],
                ['ADMIN_EMPRESA',  $adminEmpresa->permissions()->count()],
                ['EMPLEADO',       $empleado->permissions()->count()],
                ['AFILIADO',       $afiliado->permissions()->count()],
                ['CONDUCTOR',      $conductor->permissions()->count()],
            ]
        );
    }
}
