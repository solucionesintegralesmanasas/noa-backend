<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear el permiso con su módulo y descripción
        $permiso = Permission::firstOrCreate(
            ['name' => 'fuec.renew', 'guard_name' => 'api'],
            [
                'module' => 'FUEC',
                'description' => 'Renovar extractos de contrato FUEC',
            ]
        );

        // 2. Asignar el permiso exclusivamente a SUPERADMIN y ADMIN_EMPRESA
        $superAdmin = Role::where('name', 'SUPERADMIN')->where('guard_name', 'api')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permiso);
        }

        $adminEmpresa = Role::where('name', 'ADMIN_EMPRESA')->where('guard_name', 'api')->first();
        if ($adminEmpresa) {
            $adminEmpresa->givePermissionTo($permiso);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::where('name', 'fuec.renew')->where('guard_name', 'api')->first();
        if ($permiso) {
            $superAdmin = Role::where('name', 'SUPERADMIN')->where('guard_name', 'api')->first();
            if ($superAdmin) {
                $superAdmin->revokePermissionTo($permiso);
            }

            $adminEmpresa = Role::where('name', 'ADMIN_EMPRESA')->where('guard_name', 'api')->first();
            if ($adminEmpresa) {
                $adminEmpresa->revokePermissionTo($permiso);
            }

            $permiso->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
