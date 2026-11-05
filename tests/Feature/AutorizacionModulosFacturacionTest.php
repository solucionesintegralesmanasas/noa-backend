<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Los módulos de facturación y combustible exigen permisos explícitos, en vez
 * de depender solo del modo auditoría de `authz`.
 */
class AutorizacionModulosFacturacionTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private User $admin;

    private User $sinPermisos;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresa = $this->insertar('companies', [
            'business_name' => 'Autorización SAS',
            'is_active' => true,
        ]);

        $permisos = [
            'billing_providers.index', 'billing_providers.create',
            'billing_providers.update', 'billing_providers.delete',
            'fuel_logs.index', 'fuel_logs.create', 'fuel_logs.update', 'fuel_logs.delete',
        ];
        $rol = Role::findOrCreate('ADMIN_EMPRESA', 'api');
        foreach ($permisos as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'api'));
        }

        $this->admin = $this->crearUsuario('admin.auth@test.local', 'ADMIN_EMPRESA');
        $this->sinPermisos = $this->crearUsuario('sin.permisos@test.local', null);
    }

    private function crearUsuario(string $email, ?string $rol): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.$email,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        if ($rol) {
            $user->assignRole($rol);
        }
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'third_party_uuid' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function como(User $user)
    {
        Sanctum::actingAs($user, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresa]);
    }

    public function test_el_combustible_exige_sus_permisos(): void
    {
        $this->como($this->sinPermisos)
            ->getJson('/api/v1/fleet-management/fuel-logs')
            ->assertForbidden();

        $this->como($this->sinPermisos)
            ->postJson('/api/v1/fleet-management/fuel-logs', [])
            ->assertForbidden();

        $this->como($this->admin)
            ->getJson('/api/v1/fleet-management/fuel-logs')
            ->assertOk();
    }
}
