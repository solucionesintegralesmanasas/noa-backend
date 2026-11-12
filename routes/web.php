<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ---------------------------------------------------------------------------
// Rutas para ejecutar seeders (solo en entorno local/testing)
// ---------------------------------------------------------------------------
Route::middleware(['web'])->group(function () {
    // Ejecutar PermissionSeeder
    Route::get('/admin/seed/permissions', function () {
        if (app()->environment('production')) {
            return response()->json(['error' => 'No permitido en producción'], 403);
        }
        Artisan::call('db:seed', ['--class' => 'PermissionSeeder', '--force' => true]);
        return response()->json([
            'message' => 'PermissionSeeder ejecutado',
            'output' => Artisan::output(),
        ]);
    })->name('admin.seed.permissions');

    // Ejecutar RoleSeeder
    Route::get('/admin/seed/roles', function () {
        if (app()->environment('production')) {
            return response()->json(['error' => 'No permitido en producción'], 403);
        }
        Artisan::call('db:seed', ['--class' => 'RoleSeeder', '--force' => true]);
        return response()->json([
            'message' => 'RoleSeeder ejecutado',
            'output' => Artisan::output(),
        ]);
    })->name('admin.seed.roles');

    // Ejecutar ambos seeders (permisos + roles)
    Route::get('/admin/seed/permissions-roles', function () {
        if (app()->environment('production')) {
            return response()->json(['error' => 'No permitido en producción'], 403);
        }
        Artisan::call('db:seed', ['--class' => 'PermissionSeeder', '--force' => true]);
        $permOutput = Artisan::output();
        Artisan::call('db:seed', ['--class' => 'RoleSeeder', '--force' => true]);
        $roleOutput = Artisan::output();
        return response()->json([
            'message' => 'PermissionSeeder + RoleSeeder ejecutados',
            'permissions' => $permOutput,
            'roles' => $roleOutput,
        ]);
    })->name('admin.seed.permissions-roles');
});
