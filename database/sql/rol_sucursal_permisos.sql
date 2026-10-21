-- ============================================================================
-- Rol SUCURSAL y sus permisos (81 permisos, sin duplicados)
-- Base: MySQL / MariaDB
--
-- Idempotente:
--   * Crea el rol solo si no existe.
--   * Sincroniza EXACTAMENTE el set de permisos de SUCURSAL: quita los que no
--     estén en la lista y agrega los que falten.
--   * Los permisos que no existan en la base se ignoran (no rompe el script).
--
-- Uso:
--   mysql -u root -p NOMBRE_BASE < rol_sucursal_permisos.sql
--
-- Nota: no se ejecuta RoleSeeder sobre bases existentes porque reescribe los
-- permisos de todos los roles. Este script toca solo SUCURSAL.
-- ============================================================================

START TRANSACTION;

-- 1. Crear el rol si no existe -----------------------------------------------
INSERT INTO roles (name, guard_name, created_at, updated_at)
SELECT 'SUCURSAL', 'api', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE name = 'SUCURSAL' AND guard_name = 'api'
);

-- 2. Sincronizar los permisos del rol ----------------------------------------
DELETE rp
FROM role_has_permissions rp
JOIN roles r ON r.id = rp.role_id
WHERE r.name = 'SUCURSAL' AND r.guard_name = 'api';

INSERT INTO role_has_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.guard_name = r.guard_name
WHERE r.name = 'SUCURSAL'
  AND r.guard_name = 'api'
  AND p.name IN (
    -- Clientes
    'customer.index',
    'customer.create',
    'customer.update',
    'customer.delete',
    'customer.profile',

    -- Proveedores
    'supplier.index',
    'supplier.create',
    'supplier.update',
    'supplier.delete',
    'supplier.profile',

    -- Empleados
    'employee.index',
    'employee.create',
    'employee.update',
    'employee.delete',
    'employee.profile',

    -- Vehículos
    'vehicles.index',
    'vehicles.create',
    'vehicles.update',
    'vehicles.delete',
    'vehicles.profile',
    'vehicles.change_branch',
    'vehicles.history_pdf',
    'vehicles.technical_sheet_pdf',

    -- Reporte de vehículos
    'reports.vehicles.index',
    'reports.vehicles.export-excel',
    'reports.vehicles.export-pdf',

    -- Documentos vehiculares
    'vehicle_documents.index',
    'vehicle_documents.create',
    'vehicle_documents.update',
    'vehicle_documents.delete',
    'vehicle_documents.profile',

    -- Tarjetas de operación
    'operation_cards.index',
    'operation_cards.create',
    'operation_cards.update',
    'operation_cards.delete',

    -- Convenios de colaboración
    'business_collaboration_agreements.index',
    'business_collaboration_agreements.create',
    'business_collaboration_agreements.update',
    'business_collaboration_agreements.delete',

    -- Mantenimiento
    'maintenance.index',
    'maintenance.create',
    'maintenance.update',
    'maintenance.delete',
    'maintenance.forecast',
    'maintenance_parts.index',
    'maintenance_parts.create',
    'maintenance_parts.update',

    -- Combustible
    'fuel_logs.index',
    'fuel_logs.create',
    'fuel_logs.update',

    -- Inspecciones
    'vehicle_inspections.index',
    'vehicle_inspections.create',
    'vehicle_inspections.update',
    'inspection_results.index',
    'inspection_results.create',
    'inspection_results.update',

    -- Licencias de conducción
    'driver_licenses.index',
    'driver_licenses.create',
    'driver_licenses.update',

    -- Aportes de seguridad
    'security_contributions.index',
    'security_contributions.create',
    'security_contributions.update',

    -- Contratistas
    'contractors.index',
    'contractors.create',
    'contractors.update',

    -- FUEC
    'fuec.index',
    'fuec.create',
    'fuec.update',
    'fuec.profile',

    -- Planillas de control
    'control_sheets.index',
    'control_sheets.create',
    'service_delivery_control_sheets.index',
    'service_delivery_control_sheets.create',
    'service_delivery_control_sheets.history_pdf',

    -- Rastreo
    'locations.track',

    -- Catálogos compartidos (marcas y clases de vehículo)
    'brands.index',
    'brands.create',
    'brands.update',
    'vehicle_classes.index',
    'vehicle_classes.create',
    'vehicle_classes.update'
  );

COMMIT;

-- 3. Verificación ------------------------------------------------------------
SELECT
    r.id,
    r.name,
    COUNT(rp.permission_id) AS permisos_asignados,
    COUNT(DISTINCT p.name)  AS permisos_distintos
FROM roles r
LEFT JOIN role_has_permissions rp ON rp.role_id = r.id
LEFT JOIN permissions p ON p.id = rp.permission_id
WHERE r.name = 'SUCURSAL' AND r.guard_name = 'api'
GROUP BY r.id, r.name;

-- Permisos de la lista que no existen en esta base (debe salir vacío).
SELECT t.name AS permiso_inexistente
FROM (
    SELECT 'customer.index' AS name UNION ALL SELECT 'customer.create' UNION ALL SELECT 'customer.update'
    UNION ALL SELECT 'customer.delete' UNION ALL SELECT 'customer.profile'
    UNION ALL SELECT 'supplier.index' UNION ALL SELECT 'supplier.create' UNION ALL SELECT 'supplier.update'
    UNION ALL SELECT 'supplier.delete' UNION ALL SELECT 'supplier.profile'
    UNION ALL SELECT 'employee.index' UNION ALL SELECT 'employee.create' UNION ALL SELECT 'employee.update'
    UNION ALL SELECT 'employee.delete' UNION ALL SELECT 'employee.profile'
    UNION ALL SELECT 'vehicles.index' UNION ALL SELECT 'vehicles.create' UNION ALL SELECT 'vehicles.update'
    UNION ALL SELECT 'vehicles.delete' UNION ALL SELECT 'vehicles.profile' UNION ALL SELECT 'vehicles.change_branch'
    UNION ALL SELECT 'vehicles.history_pdf' UNION ALL SELECT 'vehicles.technical_sheet_pdf'
    UNION ALL SELECT 'reports.vehicles.index' UNION ALL SELECT 'reports.vehicles.export-excel'
    UNION ALL SELECT 'reports.vehicles.export-pdf'
    UNION ALL SELECT 'vehicle_documents.index' UNION ALL SELECT 'vehicle_documents.create'
    UNION ALL SELECT 'vehicle_documents.update' UNION ALL SELECT 'vehicle_documents.delete'
    UNION ALL SELECT 'vehicle_documents.profile'
    UNION ALL SELECT 'operation_cards.index' UNION ALL SELECT 'operation_cards.create'
    UNION ALL SELECT 'operation_cards.update' UNION ALL SELECT 'operation_cards.delete'
    UNION ALL SELECT 'business_collaboration_agreements.index'
    UNION ALL SELECT 'business_collaboration_agreements.create'
    UNION ALL SELECT 'business_collaboration_agreements.update'
    UNION ALL SELECT 'business_collaboration_agreements.delete'
    UNION ALL SELECT 'maintenance.index' UNION ALL SELECT 'maintenance.create' UNION ALL SELECT 'maintenance.update'
    UNION ALL SELECT 'maintenance.delete' UNION ALL SELECT 'maintenance.forecast'
    UNION ALL SELECT 'maintenance_parts.index' UNION ALL SELECT 'maintenance_parts.create'
    UNION ALL SELECT 'maintenance_parts.update'
    UNION ALL SELECT 'fuel_logs.index' UNION ALL SELECT 'fuel_logs.create' UNION ALL SELECT 'fuel_logs.update'
    UNION ALL SELECT 'vehicle_inspections.index' UNION ALL SELECT 'vehicle_inspections.create'
    UNION ALL SELECT 'vehicle_inspections.update'
    UNION ALL SELECT 'inspection_results.index' UNION ALL SELECT 'inspection_results.create'
    UNION ALL SELECT 'inspection_results.update'
    UNION ALL SELECT 'driver_licenses.index' UNION ALL SELECT 'driver_licenses.create'
    UNION ALL SELECT 'driver_licenses.update'
    UNION ALL SELECT 'security_contributions.index' UNION ALL SELECT 'security_contributions.create'
    UNION ALL SELECT 'security_contributions.update'
    UNION ALL SELECT 'contractors.index' UNION ALL SELECT 'contractors.create' UNION ALL SELECT 'contractors.update'
    UNION ALL SELECT 'fuec.index' UNION ALL SELECT 'fuec.create' UNION ALL SELECT 'fuec.update'
    UNION ALL SELECT 'fuec.profile'
    UNION ALL SELECT 'control_sheets.index' UNION ALL SELECT 'control_sheets.create'
    UNION ALL SELECT 'service_delivery_control_sheets.index' UNION ALL SELECT 'service_delivery_control_sheets.create'
    UNION ALL SELECT 'service_delivery_control_sheets.history_pdf'
    UNION ALL SELECT 'locations.track'
    UNION ALL SELECT 'brands.index' UNION ALL SELECT 'brands.create' UNION ALL SELECT 'brands.update'
    UNION ALL SELECT 'vehicle_classes.index' UNION ALL SELECT 'vehicle_classes.create'
    UNION ALL SELECT 'vehicle_classes.update'
) t
LEFT JOIN permissions p ON p.name = t.name AND p.guard_name = 'api'
WHERE p.id IS NULL;
