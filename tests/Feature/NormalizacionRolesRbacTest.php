<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Issue #1: Normalizar los roles del RBAC (ADMIN_EMPRESA vs administrador/admin).
 * Comprueba que hasRole('administrador'), hasRole('admin') y hasRole('ADMIN_EMPRESA')
 * resuelvan exactamente lo mismo, y que DashboardController reconozca a ADMIN_EMPRESA y SUPERADMIN.
 */
class NormalizacionRolesRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);
        foreach (['SUPERADMIN', 'ADMIN_EMPRESA', 'EMPLEADO', 'CONDUCTOR'] as $rol) {
            Role::findOrCreate($rol, 'api');
        }
    }

    private function crearUsuarioConRol(string $nombreRol): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario ' . $nombreRol,
            'email' => Str::random(8) . '@test.local',
            'password' => Hash::make('secreto123'),
            'status' => 1,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole($nombreRol);

        return $user;
    }

    private function asociarEmpresa(User $user): string
    {
        $empresaUuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();

        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $empresaUuid,
            'person_type' => 'PERSONA JURIDICA',
            'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno,
            'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa Test ' . Str::random(5),
            'municipality_uuid' => $relleno,
            'address' => 'Calle 100',
            'email' => Str::random(6) . '@empresa.test',
            'tax_regime_uuid' => $relleno,
            'web_page' => 'https://test.local',
            'legal_representative_name' => 'Rep',
            'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC',
            'legal_representative_document_number' => '12345678',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresaUuid,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $empresaUuid;
    }

    public function test_usuario_admin_empresa_resuelve_alias_administrador_y_admin(): void
    {
        $user = $this->crearUsuarioConRol('ADMIN_EMPRESA');

        $this->assertTrue($user->hasRole('ADMIN_EMPRESA'), 'Falla hasRole con nombre oficial');
        $this->assertTrue($user->hasRole('administrador'), 'Falla hasRole con alias en minuscula');
        $this->assertTrue($user->hasRole('ADMINISTRADOR'), 'Falla hasRole con alias en mayuscula');
        $this->assertTrue($user->hasRole('admin'), 'Falla hasRole con alias corto');
        $this->assertTrue($user->hasRole('ADMIN'), 'Falla hasRole con alias corto en mayuscula');
        $this->assertTrue($user->hasRole(['admin', 'otro']), 'Falla hasRole con array de alias');

        // No debe resolver como otros roles
        $this->assertFalse($user->hasRole('SUPERADMIN'));
        $this->assertFalse($user->hasRole('CONDUCTOR'));
        $this->assertFalse($user->hasRole('EMPLEADO'));
    }

    public function test_usuario_superadmin_resuelve_variantes_de_super_admin(): void
    {
        $user = $this->crearUsuarioConRol('SUPERADMIN');

        $this->assertTrue($user->hasRole('SUPERADMIN'));
        $this->assertTrue($user->hasRole('superadmin'));
        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue($user->hasRole('SUPER_ADMIN'));
        $this->assertTrue($user->hasRole('Super Admin'));

        // No debe resolver como admin de empresa
        $this->assertFalse($user->hasRole('ADMIN_EMPRESA'));
        $this->assertFalse($user->hasRole('administrador'));
    }

    public function test_dashboard_conductor_summary_permite_acceso_a_admin_empresa_y_superadmin(): void
    {
        // 1. ADMIN_EMPRESA
        $admin = $this->crearUsuarioConRol('ADMIN_EMPRESA');
        $empresaAdmin = $this->asociarEmpresa($admin);
        Sanctum::actingAs($admin, ['*']);

        $resAdmin = $this->withHeader('X-Company-UUID', $empresaAdmin)
            ->getJson('/api/v1/dashboard/conductor-summary');
        $resAdmin->assertStatus(200);

        // 2. SUPERADMIN
        $super = $this->crearUsuarioConRol('SUPERADMIN');
        $empresaSuper = $this->asociarEmpresa($super);
        Sanctum::actingAs($super, ['*']);

        $resSuper = $this->withHeader('X-Company-UUID', $empresaSuper)
            ->getJson('/api/v1/dashboard/conductor-summary');
        $resSuper->assertStatus(200);

        // 3. EMPLEADO no conductor ni admin -> 403 Forbidden
        $empleado = $this->crearUsuarioConRol('EMPLEADO');
        $empresaEmpleado = $this->asociarEmpresa($empleado);
        Sanctum::actingAs($empleado, ['*']);

        $resEmpleado = $this->withHeader('X-Company-UUID', $empresaEmpleado)
            ->getJson('/api/v1/dashboard/conductor-summary');
        $resEmpleado->assertStatus(403);
    }
}
