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
use Tests\TestCase;

/**
 * Un ADMIN_EMPRESA puede gestionar roles y asignarlos, pero sin escalar privilegios:
 * no toca SUPERADMIN, no borra roles del sistema y solo concede permisos que ya tiene.
 */
class GestionRolesAdminEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);
        foreach (['SUPERADMIN', 'ADMIN_EMPRESA', 'EMPLEADO', 'CONDUCTOR'] as $rol) {
            Role::findOrCreate($rol, 'api');
        }
        foreach (['vehicles.index', 'vehicles.create', 'companies.delete'] as $p) {
            Permission::findOrCreate($p, 'api');
        }
        // El administrador de empresa tiene vehicles.* pero NO companies.delete.
        Role::findByName('ADMIN_EMPRESA', 'api')->syncPermissions(['vehicles.index', 'vehicles.create']);
    }

    private function empresa(): string
    {
        if (isset($this->empresa)) {
            return $this->empresa;
        }
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.Str::random(5), 'municipality_uuid' => $relleno, 'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno, 'web_page' => 'https://e.test',
            'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $this->empresa = $uuid;
    }

    private function usuario(string $rol): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        $user->assignRole(Role::findOrCreate($rol, 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $this->empresa(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function comoAdmin(): array
    {
        Sanctum::actingAs($this->usuario('ADMIN_EMPRESA'), ['*']);

        return ['X-Company-UUID' => $this->empresa()];
    }

    public function test_crea_un_rol_con_permisos_que_tiene(): void
    {
        $cab = $this->comoAdmin();

        $this->postJson('/api/v1/auth/roles', ['name' => 'VENTAS', 'guard_name' => 'api', 'permissions' => ['vehicles.index']], $cab)
            ->assertSuccessful();
        $this->assertSame(['vehicles.index'], Role::findByName('VENTAS', 'api')->permissions->pluck('name')->all());
    }

    public function test_no_concede_un_permiso_que_no_tiene(): void
    {
        $cab = $this->comoAdmin();

        $this->postJson('/api/v1/auth/roles', ['name' => 'ESCALADA', 'guard_name' => 'api', 'permissions' => ['companies.delete']], $cab)
            ->assertForbidden();
        $this->assertNull(Role::query()->where('name', 'ESCALADA')->first());
    }

    public function test_no_puede_crear_ni_tocar_el_rol_superadmin(): void
    {
        $cab = $this->comoAdmin();
        $super = Role::findByName('SUPERADMIN', 'api');

        $this->postJson('/api/v1/auth/roles', ['name' => 'SUPERADMIN', 'guard_name' => 'api'], $cab)->assertStatus(422)->assertJsonPath('success', false);
        $this->putJson('/api/v1/auth/roles/'.$super->id, ['name' => 'SUPERADMIN', 'permissions' => ['vehicles.index']], $cab)->assertForbidden();
        $this->deleteJson('/api/v1/auth/roles/'.$super->id, [], $cab)->assertForbidden();
        $this->assertNotNull(Role::query()->find($super->id));
    }

    public function test_no_puede_renombrar_un_rol_a_superadmin(): void
    {
        $cab = $this->comoAdmin();
        $rol = Role::create(['name' => 'TEMPORAL', 'guard_name' => 'api']);

        $this->putJson('/api/v1/auth/roles/'.$rol->id, ['name' => 'SUPERADMIN'], $cab)->assertStatus(422);
        $this->assertSame('TEMPORAL', $rol->fresh()->name);
    }

    public function test_no_elimina_roles_del_sistema_pero_si_los_suyos(): void
    {
        $cab = $this->comoAdmin();
        $sistema = Role::findByName('EMPLEADO', 'api');
        $propio = Role::create(['name' => 'COORD_X', 'guard_name' => 'api']);

        $this->deleteJson('/api/v1/auth/roles/'.$sistema->id, [], $cab)->assertForbidden();
        $this->assertNotNull(Role::query()->find($sistema->id));

        $this->deleteJson('/api/v1/auth/roles/'.$propio->id, [], $cab)->assertSuccessful();
        $this->assertNull(Role::query()->find($propio->id));
    }

    public function test_asigna_permisos_a_un_rol_solo_si_son_suyos(): void
    {
        $cab = $this->comoAdmin();
        $rol = Role::create(['name' => 'LECTURA', 'guard_name' => 'api']);
        $propio = Permission::findByName('vehicles.index', 'api');
        $ajeno = Permission::findByName('companies.delete', 'api');
        $super = Role::findByName('SUPERADMIN', 'api');

        $this->postJson('/api/v1/auth/role-has-permissions', ['permission_id' => $propio->id, 'role_id' => $rol->id], $cab)->assertSuccessful();
        $this->postJson('/api/v1/auth/role-has-permissions', ['permission_id' => $ajeno->id, 'role_id' => $rol->id], $cab)->assertForbidden();
        $this->postJson('/api/v1/auth/role-has-permissions', ['permission_id' => $propio->id, 'role_id' => $super->id], $cab)->assertForbidden();
    }

    public function test_asigna_roles_a_usuarios_con_limites(): void
    {
        $cab = $this->comoAdmin();
        $objetivo = $this->usuario('CONDUCTOR');
        $suave = Role::create(['name' => 'SOLO_LECTURA', 'guard_name' => 'api']);
        $suave->givePermissionTo('vehicles.index');
        $fuerte = Role::create(['name' => 'BORRADOR_TOTAL', 'guard_name' => 'api']);
        $fuerte->givePermissionTo('companies.delete');
        $super = Role::findByName('SUPERADMIN', 'api');
        $tipo = User::class;

        $this->postJson('/api/v1/auth/model-has-roles', ['role_id' => $suave->id, 'model_id' => $objetivo->id, 'model_type' => $tipo], $cab)->assertSuccessful();
        $this->postJson('/api/v1/auth/model-has-roles', ['role_id' => $fuerte->id, 'model_id' => $objetivo->id, 'model_type' => $tipo], $cab)->assertForbidden();
        $this->postJson('/api/v1/auth/model-has-roles', ['role_id' => $super->id, 'model_id' => $objetivo->id, 'model_type' => $tipo], $cab)->assertForbidden();
        $this->assertTrue($objetivo->fresh()->hasRole('SOLO_LECTURA'));
        $this->assertFalse($objetivo->fresh()->hasRole('SUPERADMIN'));
    }

    public function test_la_definicion_de_permisos_sigue_siendo_de_superadmin(): void
    {
        $cab = $this->comoAdmin();

        $this->postJson('/api/v1/auth/permissions', ['name' => 'inventado.crear', 'guard_name' => 'api'], $cab)->assertForbidden();
    }

    public function test_superadmin_conserva_todas_las_facultades(): void
    {
        Sanctum::actingAs($this->usuario('SUPERADMIN'), ['*']);
        $cab = ['X-Company-UUID' => $this->empresa()];

        $this->postJson('/api/v1/auth/roles', ['name' => 'CON_BORRADO', 'guard_name' => 'api', 'permissions' => ['companies.delete']], $cab)->assertSuccessful();
    }

    public function test_un_conductor_sigue_sin_poder_gestionar_roles(): void
    {
        Sanctum::actingAs($this->usuario('CONDUCTOR'), ['*']);

        $this->postJson('/api/v1/auth/roles', ['name' => 'X', 'guard_name' => 'api'], ['X-Company-UUID' => $this->empresa()])->assertForbidden();
    }
}
