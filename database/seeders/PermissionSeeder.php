<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // --- Empresas ---
            'companies.index'       => ['module' => 'Empresas', 'description' => 'Listar todas las empresas'],
            'companies.create'      => ['module' => 'Empresas', 'description' => 'Crear una nueva empresa'],
            'companies.update'      => ['module' => 'Empresas', 'description' => 'Actualizar datos de una empresa'],
            'companies.delete'      => ['module' => 'Empresas', 'description' => 'Eliminar una empresa'],
            'companies.profile'     => ['module' => 'Empresas', 'description' => 'Ver el perfil detallado de una empresa'],

            // --- Contactos ---
            'contacts.index'        => ['module' => 'Contactos', 'description' => 'Listar todos los contactos'],
            'contacts.create'       => ['module' => 'Contactos', 'description' => 'Crear un nuevo contacto'],
            'contacts.update'       => ['module' => 'Contactos', 'description' => 'Actualizar datos de un contacto'],
            'contacts.delete'       => ['module' => 'Contactos', 'description' => 'Eliminar un contacto'],

            // --- Sucursales ---
            'branches.index'        => ['module' => 'Sucursales', 'description' => 'Listar todas las sucursales'],
            'branches.create'       => ['module' => 'Sucursales', 'description' => 'Crear una nueva sucursal'],
            'branches.update'       => ['module' => 'Sucursales', 'description' => 'Actualizar datos de una sucursal'],
            'branches.delete'       => ['module' => 'Sucursales', 'description' => 'Eliminar una sucursal'],
            'branches.profile'      => ['module' => 'Sucursales', 'description' => 'Ver el perfil detallado de una sucursal'],

            // --- Actividades Económicas ---
            'economic_activities.index'   => ['module' => 'Actividades Económicas', 'description' => 'Listar todas las actividades económicas'],
            'economic_activities.create'  => ['module' => 'Actividades Económicas', 'description' => 'Registrar una nueva actividad económica'],
            'economic_activities.update'  => ['module' => 'Actividades Económicas', 'description' => 'Editar una actividad económica'],
            'economic_activities.delete'  => ['module' => 'Actividades Económicas', 'description' => 'Eliminar una actividad económica'],

            // --- Datos Bancarios ---
            'bank_details.index'    => ['module' => 'Datos Bancarios', 'description' => 'Listar todos los datos bancarios'],
            'bank_details.create'   => ['module' => 'Datos Bancarios', 'description' => 'Registrar nuevos datos bancarios'],
            'bank_details.update'   => ['module' => 'Datos Bancarios', 'description' => 'Actualizar datos bancarios'],
            'bank_details.delete'   => ['module' => 'Datos Bancarios', 'description' => 'Eliminar datos bancarios'],

            // --- Información Tributaria ---
            'tax_information.index'     => ['module' => 'Información Tributaria', 'description' => 'Listar toda la información tributaria'],
            'tax_information.create'    => ['module' => 'Información Tributaria', 'description' => 'Registrar nueva información tributaria'],
            'tax_information.update'    => ['module' => 'Información Tributaria', 'description' => 'Actualizar información tributaria'],
            'tax_information.delete'    => ['module' => 'Información Tributaria', 'description' => 'Eliminar información tributaria'],

            // --- Resoluciones de Habilitación ---
            'enabling_resolutions.index'  => ['module' => 'Resoluciones de Habilitación', 'description' => 'Listar todas las resoluciones de habilitación'],
            'enabling_resolutions.create' => ['module' => 'Resoluciones de Habilitación', 'description' => 'Crear una nueva resolución de habilitación'],
            'enabling_resolutions.update' => ['module' => 'Resoluciones de Habilitación', 'description' => 'Actualizar una resolución de habilitación'],
            'enabling_resolutions.delete' => ['module' => 'Resoluciones de Habilitación', 'description' => 'Eliminar una resolución de habilitación'],

            // --- Capacidad de Transportadora ---
            'conveyor_capacity.index'  => ['module' => 'Capacidad de Transportadora', 'description' => 'Listar todas las capacidades de transportadora'],
            'conveyor_capacity.create' => ['module' => 'Capacidad de Transportadora', 'description' => 'Registrar una nueva capacidad de transportadora'],
            'conveyor_capacity.update' => ['module' => 'Capacidad de Transportadora', 'description' => 'Editar una capacidad de transportadora'],
            'conveyor_capacity.delete' => ['module' => 'Capacidad de Transportadora', 'description' => 'Eliminar una capacidad de transportadora'],

            // --- Experiencias ---
            'experiences.index'     => ['module' => 'Experiencias', 'description' => 'Listar todas las experiencias'],
            'experiences.create'    => ['module' => 'Experiencias', 'description' => 'Registrar una nueva experiencia'],
            'experiences.update'    => ['module' => 'Experiencias', 'description' => 'Actualizar una experiencia'],
            'experiences.delete'    => ['module' => 'Experiencias', 'description' => 'Eliminar una experiencia'],

            // --- Registros RUP ---
            'rup_records.index'     => ['module' => 'Registros RUP', 'description' => 'Listar todos los registros RUP'],
            'rup_records.create'    => ['module' => 'Registros RUP', 'description' => 'Crear un nuevo registro RUP'],
            'rup_records.update'    => ['module' => 'Registros RUP', 'description' => 'Actualizar un registro RUP'],
            'rup_records.delete'    => ['module' => 'Registros RUP', 'description' => 'Eliminar un registro RUP'],

            // --- Estados Financieros ---
            'financial_statements.index'   => ['module' => 'Estados Financieros', 'description' => 'Listar todos los estados financieros'],
            'financial_statements.create'  => ['module' => 'Estados Financieros', 'description' => 'Registrar un nuevo estado financiero'],
            'financial_statements.update'  => ['module' => 'Estados Financieros', 'description' => 'Actualizar un estado financiero'],
            'financial_statements.delete'  => ['module' => 'Estados Financieros', 'description' => 'Eliminar un estado financiero'],
            'financial_statements.profile' => ['module' => 'Estados Financieros', 'description' => 'Ver el perfil detallado de un estado financiero'],

            // --- Declaraciones Tributarias ---
            'tax_declarations.index'    => ['module' => 'Declaraciones Tributarias', 'description' => 'Listar todas las declaraciones tributarias'],
            'tax_declarations.create'   => ['module' => 'Declaraciones Tributarias', 'description' => 'Crear una nueva declaración tributaria'],
            'tax_declarations.update'   => ['module' => 'Declaraciones Tributarias', 'description' => 'Actualizar una declaración tributaria'],
            'tax_declarations.delete'   => ['module' => 'Declaraciones Tributarias', 'description' => 'Eliminar una declaración tributaria'],
            'tax_declarations.profile'  => ['module' => 'Declaraciones Tributarias', 'description' => 'Ver el perfil detallado de una declaración tributaria'],

            // --- Registros de Seguridad Ocupacional ---
            'occupational_safety_records.index'   => ['module' => 'Seguridad Ocupacional', 'description' => 'Listar todos los registros de seguridad ocupacional'],
            'occupational_safety_records.create'  => ['module' => 'Seguridad Ocupacional', 'description' => 'Registrar un nuevo registro de seguridad ocupacional'],
            'occupational_safety_records.update'  => ['module' => 'Seguridad Ocupacional', 'description' => 'Actualizar un registro de seguridad ocupacional'],
            'occupational_safety_records.delete'  => ['module' => 'Seguridad Ocupacional', 'description' => 'Eliminar un registro de seguridad ocupacional'],
            'occupational_safety_records.profile' => ['module' => 'Seguridad Ocupacional', 'description' => 'Ver el perfil detallado de un registro de seguridad ocupacional'],

            // --- Clientes ---
            'customer.index'    => ['module' => 'Clientes', 'description' => 'Listar todos los clientes'],
            'customer.create'   => ['module' => 'Clientes', 'description' => 'Crear un nuevo cliente'],
            'customer.update'   => ['module' => 'Clientes', 'description' => 'Actualizar datos de un cliente'],
            'customer.delete'   => ['module' => 'Clientes', 'description' => 'Eliminar un cliente'],
            'customer.profile'  => ['module' => 'Clientes', 'description' => 'Ver el perfil detallado de un cliente'],

            // --- Proveedores ---
            'supplier.index'    => ['module' => 'Proveedores', 'description' => 'Listar todos los proveedores'],
            'supplier.create'   => ['module' => 'Proveedores', 'description' => 'Registrar un nuevo proveedor'],
            'supplier.update'   => ['module' => 'Proveedores', 'description' => 'Actualizar datos de un proveedor'],
            'supplier.delete'   => ['module' => 'Proveedores', 'description' => 'Eliminar un proveedor'],
            'supplier.profile'  => ['module' => 'Proveedores', 'description' => 'Ver el perfil detallado de un proveedor'],

            // --- Empleados ---
            'employee.index'    => ['module' => 'Empleados', 'description' => 'Listar todos los empleados'],
            'employee.create'   => ['module' => 'Empleados', 'description' => 'Registrar un nuevo empleado'],
            'employee.update'   => ['module' => 'Empleados', 'description' => 'Actualizar datos de un empleado'],
            'employee.delete'   => ['module' => 'Empleados', 'description' => 'Eliminar un empleado'],
            'employee.profile'  => ['module' => 'Empleados', 'description' => 'Ver el perfil detallado de un empleado'],

            // --- Contratos Laborales ---
            'employmentContracts.index'  => ['module' => 'Contratos Laborales', 'description' => 'Listar todos los contratos laborales'],
            'employmentContracts.create' => ['module' => 'Contratos Laborales', 'description' => 'Crear un nuevo contrato laboral'],
            'employmentContracts.update' => ['module' => 'Contratos Laborales', 'description' => 'Actualizar un contrato laboral'],
            'employmentContracts.delete' => ['module' => 'Contratos Laborales', 'description' => 'Eliminar un contrato laboral'],
            'employmentContracts.show'   => ['module' => 'Contratos Laborales', 'description' => 'Ver detalles de un contrato laboral'],

            // --- Afiliados ---
            'affiliate.index'    => ['module' => 'Afiliados', 'description' => 'Listar todos los afiliados'],
            'affiliate.create'   => ['module' => 'Afiliados', 'description' => 'Registrar un nuevo afiliado'],
            'affiliate.update'   => ['module' => 'Afiliados', 'description' => 'Actualizar datos de un afiliado'],
            'affiliate.delete'   => ['module' => 'Afiliados', 'description' => 'Eliminar un afiliado'],
            'affiliate.profile'  => ['module' => 'Afiliados', 'description' => 'Ver el perfil detallado de un afiliado'],

            // --- Conductores ---
            'driver.index'    => ['module' => 'Conductores', 'description' => 'Listar todos los conductores'],
            'driver.create'   => ['module' => 'Conductores', 'description' => 'Registrar un nuevo conductor'],
            'driver.update'   => ['module' => 'Conductores', 'description' => 'Actualizar datos de un conductor'],
            'driver.delete'   => ['module' => 'Conductores', 'description' => 'Eliminar un conductor'],
            'driver.profile'  => ['module' => 'Conductores', 'description' => 'Ver el perfil detallado de un conductor'],

            // --- Terceros ---
            'third_parties.index'    => ['module' => 'Terceros', 'description' => 'Listar todos los terceros'],
            'third_parties.create'   => ['module' => 'Terceros', 'description' => 'Registrar un nuevo tercero'],
            'third_parties.update'   => ['module' => 'Terceros', 'description' => 'Actualizar datos de un tercero'],
            'third_parties.delete'   => ['module' => 'Terceros', 'description' => 'Eliminar un tercero'],
            'third_parties.profile'  => ['module' => 'Terceros', 'description' => 'Ver el perfil detallado de un tercero'],

            // --- Categorías ---
            'categories.index'     => ['module' => 'Categorías', 'description' => 'Listar todas las categorías'],
            'categories.create'    => ['module' => 'Categorías', 'description' => 'Crear una nueva categoría'],
            'categories.update'    => ['module' => 'Categorías', 'description' => 'Actualizar una categoría'],
            'categories.delete'    => ['module' => 'Categorías', 'description' => 'Eliminar una categoría'],

            // --- Almacenes ---
            'warehouses.index'     => ['module' => 'Almacenes', 'description' => 'Listar todos los almacenes'],
            'warehouses.create'    => ['module' => 'Almacenes', 'description' => 'Crear un nuevo almacén'],
            'warehouses.update'    => ['module' => 'Almacenes', 'description' => 'Actualizar un almacén'],
            'warehouses.delete'    => ['module' => 'Almacenes', 'description' => 'Eliminar un almacén'],

            // --- Productos ---
            'products.index'       => ['module' => 'Productos', 'description' => 'Listar todos los productos'],
            'products.create'      => ['module' => 'Productos', 'description' => 'Crear un nuevo producto'],
            'products.update'      => ['module' => 'Productos', 'description' => 'Actualizar un producto'],
            'products.delete'      => ['module' => 'Productos', 'description' => 'Eliminar un producto'],

            // --- Variantes de Productos ---
            'product_variants.index'   => ['module' => 'Variantes de Productos', 'description' => 'Listar todas las variantes de productos'],
            'product_variants.create'  => ['module' => 'Variantes de Productos', 'description' => 'Crear una nueva variante de producto'],
            'product_variants.update'  => ['module' => 'Variantes de Productos', 'description' => 'Actualizar una variante de producto'],
            'product_variants.delete'  => ['module' => 'Variantes de Productos', 'description' => 'Eliminar una variante de producto'],

            // --- Plan de Cuentas ---
            'chart_of_accounts.index'  => ['module' => 'Plan de Cuentas', 'description' => 'Listar todas las cuentas del plan contable'],
            'chart_of_accounts.create' => ['module' => 'Plan de Cuentas', 'description' => 'Crear una nueva cuenta contable'],
            'chart_of_accounts.update' => ['module' => 'Plan de Cuentas', 'description' => 'Actualizar una cuenta contable'],
            'chart_of_accounts.delete' => ['module' => 'Plan de Cuentas', 'description' => 'Eliminar una cuenta contable'],

            // --- Centros de Costos ---
            'cost_centers.index'   => ['module' => 'Centros de Costos', 'description' => 'Listar todos los centros de costos'],
            'cost_centers.create'  => ['module' => 'Centros de Costos', 'description' => 'Crear un nuevo centro de costos'],
            'cost_centers.update'  => ['module' => 'Centros de Costos', 'description' => 'Actualizar un centro de costos'],
            'cost_centers.delete'  => ['module' => 'Centros de Costos', 'description' => 'Eliminar un centro de costos'],

            // --- Tipos de Comprobantes ---
            'voucher_types.index'  => ['module' => 'Tipos de Comprobantes', 'description' => 'Listar todos los tipos de comprobantes'],
            'voucher_types.create' => ['module' => 'Tipos de Comprobantes', 'description' => 'Crear un nuevo tipo de comprobante'],
            'voucher_types.update' => ['module' => 'Tipos de Comprobantes', 'description' => 'Actualizar un tipo de comprobante'],
            'voucher_types.delete' => ['module' => 'Tipos de Comprobantes', 'description' => 'Eliminar un tipo de comprobante'],

            // --- Cuentas Bancarias ---
            'bank_accounts.index'   => ['module' => 'Cuentas Bancarias', 'description' => 'Listar todas las cuentas bancarias'],
            'bank_accounts.create'  => ['module' => 'Cuentas Bancarias', 'description' => 'Crear una nueva cuenta bancaria'],
            'bank_accounts.update'  => ['module' => 'Cuentas Bancarias', 'description' => 'Actualizar una cuenta bancaria'],
            'bank_accounts.delete'  => ['module' => 'Cuentas Bancarias', 'description' => 'Eliminar una cuenta bancaria'],

            // --- Presupuestos ---
            'budgets.index'     => ['module' => 'Presupuestos', 'description' => 'Listar todos los presupuestos'],
            'budgets.create'    => ['module' => 'Presupuestos', 'description' => 'Crear un nuevo presupuesto'],
            'budgets.update'    => ['module' => 'Presupuestos', 'description' => 'Actualizar un presupuesto'],
            'budgets.delete'    => ['module' => 'Presupuestos', 'description' => 'Eliminar un presupuesto'],

            // --- Proveedores de Facturación ---
            'billing_providers.index'  => ['module' => 'Proveedores de Facturación', 'description' => 'Listar todos los proveedores de facturación'],
            'billing_providers.create' => ['module' => 'Proveedores de Facturación', 'description' => 'Registrar un nuevo proveedor de facturación'],
            'billing_providers.update' => ['module' => 'Proveedores de Facturación', 'description' => 'Actualizar un proveedor de facturación'],
            'billing_providers.delete' => ['module' => 'Proveedores de Facturación', 'description' => 'Eliminar un proveedor de facturación'],

            // --- Resoluciones de Facturación ---
            'billing_resolutions.index'   => ['module' => 'Resoluciones de Facturación', 'description' => 'Listar todas las resoluciones de facturación'],
            'billing_resolutions.create'  => ['module' => 'Resoluciones de Facturación', 'description' => 'Crear una nueva resolución de facturación'],
            'billing_resolutions.update'  => ['module' => 'Resoluciones de Facturación', 'description' => 'Actualizar una resolución de facturación'],
            'billing_resolutions.delete'  => ['module' => 'Resoluciones de Facturación', 'description' => 'Eliminar una resolución de facturación'],

            // --- Documentos Fiscales ---
            'fiscal_documents.index'  => ['module' => 'Documentos Fiscales', 'description' => 'Listar todos los documentos fiscales'],
            'fiscal_documents.create' => ['module' => 'Documentos Fiscales', 'description' => 'Crear un nuevo documento fiscal'],
            'fiscal_documents.update' => ['module' => 'Documentos Fiscales', 'description' => 'Actualizar un documento fiscal'],
            'fiscal_documents.delete' => ['module' => 'Documentos Fiscales', 'description' => 'Eliminar un documento fiscal'],

            // --- Facturas Comerciales ---
            'commercial_invoices.index'  => ['module' => 'Facturas Comerciales', 'description' => 'Listar todas las facturas comerciales'],
            'commercial_invoices.create' => ['module' => 'Facturas Comerciales', 'description' => 'Crear una nueva factura comercial'],
            'commercial_invoices.update' => ['module' => 'Facturas Comerciales', 'description' => 'Actualizar una factura comercial'],
            'commercial_invoices.delete' => ['module' => 'Facturas Comerciales', 'description' => 'Eliminar una factura comercial'],

            // --- Facturas Electrónicas ---
            'electronic_invoices.index'  => ['module' => 'Facturas Electrónicas', 'description' => 'Listar todas las facturas electrónicas'],
            'electronic_invoices.create' => ['module' => 'Facturas Electrónicas', 'description' => 'Generar una nueva factura electrónica'],
            'electronic_invoices.update' => ['module' => 'Facturas Electrónicas', 'description' => 'Actualizar una factura electrónica'],
            'electronic_invoices.delete' => ['module' => 'Facturas Electrónicas', 'description' => 'Eliminar una factura electrónica'],

            // --- Notas Crédito ---
            'credit_notes.index'  => ['module' => 'Notas Crédito', 'description' => 'Listar todas las notas crédito'],
            'credit_notes.create' => ['module' => 'Notas Crédito', 'description' => 'Crear una nueva nota crédito'],
            'credit_notes.update' => ['module' => 'Notas Crédito', 'description' => 'Actualizar una nota crédito'],
            'credit_notes.delete' => ['module' => 'Notas Crédito', 'description' => 'Eliminar una nota crédito'],

            // --- Notas Débito ---
            'debit_notes.index'   => ['module' => 'Notas Débito', 'description' => 'Listar todas las notas débito'],
            'debit_notes.create'  => ['module' => 'Notas Débito', 'description' => 'Crear una nueva nota débito'],
            'debit_notes.update'  => ['module' => 'Notas Débito', 'description' => 'Actualizar una nota débito'],
            'debit_notes.delete'  => ['module' => 'Notas Débito', 'description' => 'Eliminar una nota débito'],

            // --- Órdenes de Compra ---
            'purchase_orders.index'  => ['module' => 'Órdenes de Compra', 'description' => 'Listar todas las órdenes de compra'],
            'purchase_orders.create' => ['module' => 'Órdenes de Compra', 'description' => 'Crear una nueva orden de compra'],
            'purchase_orders.update' => ['module' => 'Órdenes de Compra', 'description' => 'Actualizar una orden de compra'],
            'purchase_orders.delete' => ['module' => 'Órdenes de Compra', 'description' => 'Eliminar una orden de compra'],

            // --- Compras ---
            'purchases.index'  => ['module' => 'Compras', 'description' => 'Listar todas las compras'],
            'purchases.create' => ['module' => 'Compras', 'description' => 'Registrar una nueva compra'],
            'purchases.update' => ['module' => 'Compras', 'description' => 'Actualizar una compra'],
            'purchases.delete' => ['module' => 'Compras', 'description' => 'Eliminar una compra'],

            // --- Documentos de Soporte ---
            'support_documents.index'  => ['module' => 'Documentos de Soporte', 'description' => 'Listar todos los documentos de soporte'],
            'support_documents.create' => ['module' => 'Documentos de Soporte', 'description' => 'Subir un nuevo documento de soporte'],
            'support_documents.update' => ['module' => 'Documentos de Soporte', 'description' => 'Actualizar un documento de soporte'],
            'support_documents.delete' => ['module' => 'Documentos de Soporte', 'description' => 'Eliminar un documento de soporte'],

            // --- Notas de Ajuste ---
            'adjustment_notes.index'  => ['module' => 'Notas de Ajuste', 'description' => 'Listar todas las notas de ajuste'],
            'adjustment_notes.create' => ['module' => 'Notas de Ajuste', 'description' => 'Crear una nueva nota de ajuste'],
            'adjustment_notes.update' => ['module' => 'Notas de Ajuste', 'description' => 'Actualizar una nota de ajuste'],
            'adjustment_notes.delete' => ['module' => 'Notas de Ajuste', 'description' => 'Eliminar una nota de ajuste'],

            // --- Periodos Tributarios ---
            'tax_periods.index'  => ['module' => 'Periodos Tributarios', 'description' => 'Listar todos los periodos tributarios'],
            'tax_periods.create' => ['module' => 'Periodos Tributarios', 'description' => 'Crear un nuevo periodo tributario'],
            'tax_periods.update' => ['module' => 'Periodos Tributarios', 'description' => 'Actualizar un periodo tributario'],
            'tax_periods.delete' => ['module' => 'Periodos Tributarios', 'description' => 'Eliminar un periodo tributario'],

            // --- Documentos Electrónicos ---
            'electronic_documents.index'  => ['module' => 'Documentos Electrónicos', 'description' => 'Listar todos los documentos electrónicos'],
            'electronic_documents.create' => ['module' => 'Documentos Electrónicos', 'description' => 'Generar un nuevo documento electrónico'],
            'electronic_documents.update' => ['module' => 'Documentos Electrónicos', 'description' => 'Actualizar un documento electrónico'],
            'electronic_documents.delete' => ['module' => 'Documentos Electrónicos', 'description' => 'Eliminar un documento electrónico'],

            // --- Cobros de Administración de Afiliados ---
            'affiliate_admin_charges.index'  => ['module' => 'Cobros de Administración', 'description' => 'Listar todos los cobros de administración de afiliados'],
            'affiliate_admin_charges.create' => ['module' => 'Cobros de Administración', 'description' => 'Registrar un nuevo cobro de administración'],
            'affiliate_admin_charges.update' => ['module' => 'Cobros de Administración', 'description' => 'Actualizar un cobro de administración'],
            'affiliate_admin_charges.delete' => ['module' => 'Cobros de Administración', 'description' => 'Eliminar un cobro de administración'],
            'affiliate_admin_charges.profile' => ['module' => 'Cobros de Administración', 'description' => 'Ver detalles de un cobro de administración'],

            // --- Extracto de Contrato ---
            'extract_of_contract.index'  => ['module' => 'Extractos de Contrato', 'description' => 'Listar todos los extractos de contrato'],
            'extract_of_contract.create' => ['module' => 'Extractos de Contrato', 'description' => 'Crear un nuevo extracto de contrato'],
            'extract_of_contract.update' => ['module' => 'Extractos de Contrato', 'description' => 'Actualizar un extracto de contrato'],
            'extract_of_contract.delete' => ['module' => 'Extractos de Contrato', 'description' => 'Eliminar un extracto de contrato'],

            // --- Vehículos ---
            'vehicles.index'           => ['module' => 'Vehículos', 'description' => 'Listar todos los vehículos'],
            'vehicles.create'          => ['module' => 'Vehículos', 'description' => 'Registrar un nuevo vehículo'],
            'vehicles.update'          => ['module' => 'Vehículos', 'description' => 'Actualizar datos de un vehículo'],
            'vehicles.delete'          => ['module' => 'Vehículos', 'description' => 'Eliminar un vehículo'],
            'vehicles.profile'         => ['module' => 'Vehículos', 'description' => 'Ver el perfil detallado de un vehículo'],
            'vehicles.change_branch'   => ['module' => 'Vehículos', 'description' => 'Cambiar la sucursal de un vehículo'],
            'vehicles.history_pdf'     => ['module' => 'Vehículos', 'description' => 'Generar PDF del historial de un vehículo'],
            'vehicles.technical_sheet_pdf' => ['module' => 'Vehículos', 'description' => 'Generar PDF de la ficha técnica de un vehículo'],

            // --- Documentos de Vehículos ---
            'vehicle_documents.index'  => ['module' => 'Documentos de Vehículos', 'description' => 'Listar todos los documentos de vehículos'],
            'vehicle_documents.create' => ['module' => 'Documentos de Vehículos', 'description' => 'Subir un nuevo documento de vehículo'],
            'vehicle_documents.update' => ['module' => 'Documentos de Vehículos', 'description' => 'Actualizar un documento de vehículo'],
            'vehicle_documents.delete' => ['module' => 'Documentos de Vehículos', 'description' => 'Eliminar un documento de vehículo'],

            // --- Tarjetas de Operación ---
            'operation_cards.index'  => ['module' => 'Tarjetas de Operación', 'description' => 'Listar todas las tarjetas de operación'],
            'operation_cards.create' => ['module' => 'Tarjetas de Operación', 'description' => 'Crear una nueva tarjeta de operación'],
            'operation_cards.update' => ['module' => 'Tarjetas de Operación', 'description' => 'Actualizar una tarjeta de operación'],
            'operation_cards.delete' => ['module' => 'Tarjetas de Operación', 'description' => 'Eliminar una tarjeta de operación'],

            // --- Acuerdos de Colaboración Empresarial ---
            'business_collaboration_agreements.index'  => ['module' => 'Acuerdos de Colaboración', 'description' => 'Listar todos los acuerdos de colaboración empresarial'],
            'business_collaboration_agreements.create' => ['module' => 'Acuerdos de Colaboración', 'description' => 'Crear un nuevo acuerdo de colaboración'],
            'business_collaboration_agreements.update' => ['module' => 'Acuerdos de Colaboración', 'description' => 'Actualizar un acuerdo de colaboración'],
            'business_collaboration_agreements.delete' => ['module' => 'Acuerdos de Colaboración', 'description' => 'Eliminar un acuerdo de colaboración'],

            // --- Mantenimiento ---
            'maintenance.index'  => ['module' => 'Mantenimiento', 'description' => 'Listar todos los registros de mantenimiento'],
            'maintenance.create' => ['module' => 'Mantenimiento', 'description' => 'Registrar un nuevo mantenimiento'],
            'maintenance.update' => ['module' => 'Mantenimiento', 'description' => 'Actualizar un registro de mantenimiento'],
            'maintenance.delete' => ['module' => 'Mantenimiento', 'description' => 'Eliminar un registro de mantenimiento'],
            'maintenance.forecast' => ['module' => 'Mantenimiento', 'description' => 'Generar pronóstico de mantenimiento'],

            // --- Repuestos de Mantenimiento ---
            'maintenance_parts.index'  => ['module' => 'Repuestos', 'description' => 'Listar todos los repuestos de mantenimiento'],
            'maintenance_parts.create' => ['module' => 'Repuestos', 'description' => 'Registrar un nuevo repuesto'],
            'maintenance_parts.update' => ['module' => 'Repuestos', 'description' => 'Actualizar un repuesto'],
            'maintenance_parts.delete' => ['module' => 'Repuestos', 'description' => 'Eliminar un repuesto'],

            // --- Inspecciones de Vehículos ---
            'vehicle_inspections.index'  => ['module' => 'Inspecciones de Vehículos', 'description' => 'Listar todas las inspecciones de vehículos'],
            'vehicle_inspections.create' => ['module' => 'Inspecciones de Vehículos', 'description' => 'Registrar una nueva inspección'],
            'vehicle_inspections.update' => ['module' => 'Inspecciones de Vehículos', 'description' => 'Actualizar una inspección'],
            'vehicle_inspections.delete' => ['module' => 'Inspecciones de Vehículos', 'description' => 'Eliminar una inspección'],

            // --- Resultados de Inspección ---
            'inspection_results.index'  => ['module' => 'Resultados de Inspección', 'description' => 'Listar todos los resultados de inspección'],
            'inspection_results.create' => ['module' => 'Resultados de Inspección', 'description' => 'Registrar un nuevo resultado de inspección'],
            'inspection_results.update' => ['module' => 'Resultados de Inspección', 'description' => 'Actualizar un resultado de inspección'],
            'inspection_results.delete' => ['module' => 'Resultados de Inspección', 'description' => 'Eliminar un resultado de inspección'],

            // --- Licencias de Conductor ---
            'driver_licenses.index'  => ['module' => 'Licencias de Conductor', 'description' => 'Listar todas las licencias de conductor'],
            'driver_licenses.create' => ['module' => 'Licencias de Conductor', 'description' => 'Registrar una nueva licencia'],
            'driver_licenses.update' => ['module' => 'Licencias de Conductor', 'description' => 'Actualizar una licencia'],
            'driver_licenses.delete' => ['module' => 'Licencias de Conductor', 'description' => 'Eliminar una licencia'],

            // --- Aportes de Seguridad ---
            'security_contributions.index'  => ['module' => 'Aportes de Seguridad', 'description' => 'Listar todos los aportes de seguridad'],
            'security_contributions.create' => ['module' => 'Aportes de Seguridad', 'description' => 'Registrar un nuevo aporte de seguridad'],
            'security_contributions.update' => ['module' => 'Aportes de Seguridad', 'description' => 'Actualizar un aporte de seguridad'],
            'security_contributions.delete' => ['module' => 'Aportes de Seguridad', 'description' => 'Eliminar un aporte de seguridad'],

            // --- Directores Territoriales ---
            'territorial_directors.index'      => ['module' => 'Directores Territoriales', 'description' => 'Listar todos los directores territoriales'],
            'territorial_directors.create'     => ['module' => 'Directores Territoriales', 'description' => 'Registrar un nuevo director territorial'],
            'territorial_directors.update'     => ['module' => 'Directores Territoriales', 'description' => 'Actualizar un director territorial'],
            'territorial_directors.delete'     => ['module' => 'Directores Territoriales', 'description' => 'Eliminar un director territorial'],
            'territorial_directors.toggle-status' => ['module' => 'Directores Territoriales', 'description' => 'Activar/Desactivar un director territorial'],

            // --- Procedimientos ---
            'procedures.index'  => ['module' => 'Procedimientos', 'description' => 'Listar todos los procedimientos'],
            'procedures.create' => ['module' => 'Procedimientos', 'description' => 'Crear un nuevo procedimiento'],
            'procedures.update' => ['module' => 'Procedimientos', 'description' => 'Actualizar un procedimiento'],
            'procedures.delete' => ['module' => 'Procedimientos', 'description' => 'Eliminar un procedimiento'],

            // --- Contratos de Servicio de Flota ---
            'fleet_service_contracts.index'  => ['module' => 'Contratos de Flota', 'description' => 'Listar todos los contratos de servicio de flota'],
            'fleet_service_contracts.create' => ['module' => 'Contratos de Flota', 'description' => 'Crear un nuevo contrato de servicio'],
            'fleet_service_contracts.update' => ['module' => 'Contratos de Flota', 'description' => 'Actualizar un contrato de servicio'],
            'fleet_service_contracts.delete' => ['module' => 'Contratos de Flota', 'description' => 'Eliminar un contrato de servicio'],

            // --- Inventario de Capacidad ---
            'capacity_inventory.index'  => ['module' => 'Inventario de Capacidad', 'description' => 'Listar todo el inventario de capacidad'],
            'capacity_inventory.create' => ['module' => 'Inventario de Capacidad', 'description' => 'Registrar un nuevo ítem en el inventario'],
            'capacity_inventory.update' => ['module' => 'Inventario de Capacidad', 'description' => 'Actualizar un ítem del inventario'],
            'capacity_inventory.delete' => ['module' => 'Inventario de Capacidad', 'description' => 'Eliminar un ítem del inventario'],

            // --- Contratos de Objetos ---
            'objects_contracts.index'  => ['module' => 'Contratos de Objetos', 'description' => 'Listar todos los contratos de objetos'],
            'objects_contracts.create' => ['module' => 'Contratos de Objetos', 'description' => 'Crear un nuevo contrato de objetos'],
            'objects_contracts.update' => ['module' => 'Contratos de Objetos', 'description' => 'Actualizar un contrato de objetos'],
            'objects_contracts.delete' => ['module' => 'Contratos de Objetos', 'description' => 'Eliminar un contrato de objetos'],

            // --- Contratistas ---
            'contractors.index'  => ['module' => 'Contratistas', 'description' => 'Listar todos los contratistas'],
            'contractors.create' => ['module' => 'Contratistas', 'description' => 'Registrar un nuevo contratista'],
            'contractors.update' => ['module' => 'Contratistas', 'description' => 'Actualizar un contratista'],
            'contractors.delete' => ['module' => 'Contratistas', 'description' => 'Eliminar un contratista'],

            // --- FUEC ---
            'fuec.index'    => ['module' => 'FUEC', 'description' => 'Listar todos los registros FUEC'],
            'fuec.create'   => ['module' => 'FUEC', 'description' => 'Registrar un nuevo FUEC'],
            'fuec.profile'  => ['module' => 'FUEC', 'description' => 'Ver el perfil detallado de un FUEC'],
            'fuec.update'   => ['module' => 'FUEC', 'description' => 'Actualizar un FUEC'],
            'fuec.delete'   => ['module' => 'FUEC', 'description' => 'Eliminar un FUEC'],

            // --- Configuraciones del Sistema ---
            'system_configurations.index'  => ['module' => 'Configuraciones del Sistema', 'description' => 'Listar todas las configuraciones del sistema'],
            'system_configurations.create' => ['module' => 'Configuraciones del Sistema', 'description' => 'Crear una nueva configuración'],
            'system_configurations.update' => ['module' => 'Configuraciones del Sistema', 'description' => 'Actualizar una configuración'],
            'system_configurations.delete' => ['module' => 'Configuraciones del Sistema', 'description' => 'Eliminar una configuración'],

            // --- Hojas de Control ---
            'control_sheets.index'  => ['module' => 'Hojas de Control', 'description' => 'Listar todas las hojas de control'],
            'control_sheets.create' => ['module' => 'Hojas de Control', 'description' => 'Crear una nueva hoja de control'],
            'control_sheets.update' => ['module' => 'Hojas de Control', 'description' => 'Actualizar una hoja de control'],
            'control_sheets.delete' => ['module' => 'Hojas de Control', 'description' => 'Eliminar una hoja de control'],

            // --- Hojas de Control de Entrega de Servicio ---
            'service_delivery_control_sheets.index'  => ['module' => 'Hojas de Entrega', 'description' => 'Listar todas las hojas de control de entrega'],
            'service_delivery_control_sheets.view'   => ['module' => 'Hojas de Entrega', 'description' => 'Ver una hoja de control de entrega'],
            'service_delivery_control_sheets.create' => ['module' => 'Hojas de Entrega', 'description' => 'Crear una nueva hoja de control de entrega'],
            'service_delivery_control_sheets.update' => ['module' => 'Hojas de Entrega', 'description' => 'Actualizar una hoja de control de entrega'],
            'service_delivery_control_sheets.edit'   => ['module' => 'Hojas de Entrega', 'description' => 'Editar una hoja de control de entrega'],
            'service_delivery_control_sheets.delete' => ['module' => 'Hojas de Entrega', 'description' => 'Eliminar una hoja de control de entrega'],
            'service_delivery_control_sheets.history_pdf' => ['module' => 'Hojas de Entrega', 'description' => 'Generar PDF del historial de entrega'],
            'service_delivery_control_sheets.technical_sheet_pdf' => ['module' => 'Hojas de Entrega', 'description' => 'Generar PDF de la ficha técnica de entrega'],
            'service_delivery_control_sheets.cold_chain_pdf' => ['module' => 'Hojas de Entrega', 'description' => 'Generar PDF de cadena de frío de entrega'],

            // --- Configuración del Asistente ---
            'assistant_configuration.index'  => ['module' => 'Configuración del Asistente', 'description' => 'Listar todas las configuraciones del asistente'],
            'assistant_configuration.create' => ['module' => 'Configuración del Asistente', 'description' => 'Crear una nueva configuración del asistente'],
            'assistant_configuration.update' => ['module' => 'Configuración del Asistente', 'description' => 'Actualizar una configuración del asistente'],
            'assistant_configuration.delete' => ['module' => 'Configuración del Asistente', 'description' => 'Eliminar una configuración del asistente'],

            // --- Asistente ---
            'assistant.index'  => ['module' => 'Asistente', 'description' => 'Listar todos los asistentes'],
            'assistant.create' => ['module' => 'Asistente', 'description' => 'Crear un nuevo asistente'],
            'assistant.update' => ['module' => 'Asistente', 'description' => 'Actualizar un asistente'],
            'assistant.delete' => ['module' => 'Asistente', 'description' => 'Eliminar un asistente'],

            // --- Usuarios ---
            'users.index'    => ['module' => 'Usuarios', 'description' => 'Listar todos los usuarios'],
            'users.create'   => ['module' => 'Usuarios', 'description' => 'Crear un nuevo usuario'],
            'users.update'   => ['module' => 'Usuarios', 'description' => 'Actualizar datos de un usuario'],
            'users.delete'   => ['module' => 'Usuarios', 'description' => 'Eliminar un usuario'],
            'users.profile'  => ['module' => 'Usuarios', 'description' => 'Ver el perfil detallado de un usuario'],

            // --- Roles y Permisos ---
            'roles.index'    => ['module' => 'Roles', 'description' => 'Listar todos los roles'],
            'roles.create'   => ['module' => 'Roles', 'description' => 'Crear un nuevo rol'],
            'roles.update'   => ['module' => 'Roles', 'description' => 'Actualizar un rol'],
            'roles.delete'   => ['module' => 'Roles', 'description' => 'Eliminar un rol'],
            'permissions.index' => ['module' => 'Permisos', 'description' => 'Listar todos los permisos'],

            // --- Proyectos ---
            'projects.index'    => ['module' => 'Proyectos', 'description' => 'Listar todos los proyectos'],
            'projects.create'   => ['module' => 'Proyectos', 'description' => 'Crear un nuevo proyecto'],
            'projects.update'   => ['module' => 'Proyectos', 'description' => 'Actualizar un proyecto'],
            'projects.delete'   => ['module' => 'Proyectos', 'description' => 'Eliminar un proyecto'],
            'projects.profile'  => ['module' => 'Proyectos', 'description' => 'Ver el perfil detallado de un proyecto'],

            // --- Geolocalización ---
            'locations.view'        => ['module' => 'Geolocalización', 'description' => 'Ver mapa de conductores en tiempo real'],
            'locations.track'       => ['module' => 'Geolocalización', 'description' => 'Enviar ubicación GPS (conductor)'],
            'locations.history'     => ['module' => 'Geolocalización', 'description' => 'Ver historial de rutas de conductores'],
            'locations.geofences'   => ['module' => 'Geolocalización', 'description' => 'Gestionar geocercas (crear, editar, eliminar)'],
            'locations.alerts'      => ['module' => 'Geolocalización', 'description' => 'Ver y gestionar alertas de ubicación'],
        ];

        foreach ($permissions as $permissionName => $data) {
            Permission::updateOrCreate(
                ['name' => $permissionName, 'guard_name' => 'api'],
                [
                    'module' => $data['module'],
                    'description' => $data['description'],
                ]
            );
        }

        // Limpieza: eliminar permisos obsoletos que ya no están definidos (garantiza "solo permisos" definidos)
        $definedNames = array_keys($permissions);
        $stalePermissions = Permission::where('guard_name', 'api')
            ->whereNotIn('name', $definedNames)
            ->get();

        if ($stalePermissions->isNotEmpty()) {
            foreach ($stalePermissions as $stale) {
                $stale->delete();
            }
            $this->command->warn('🗑️  ' . $stalePermissions->count() . ' permisos obsoletos eliminados: ' . $stalePermissions->pluck('name')->implode(', '));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ ' . count($permissions) . ' permisos creados o actualizados con éxito.');
    }
}
