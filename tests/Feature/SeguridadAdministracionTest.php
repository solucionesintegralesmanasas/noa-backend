<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCronToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase A de la revisión de seguridad: escritura de identidades protegida por rol,
 * aislamiento estricto entre empresas y endpoints de Web Cron con secreto.
 */
class SeguridadAdministracionTest extends TestCase
{
    use RefreshDatabase;

    private function empresa(): string
    {
        $uuid = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        $relleno = (string) Str::uuid();
        DB::table('companies')->insert([
            'uuid' => $uuid,
            'person_type' => 'PERSONA JURIDICA',
            'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno,
            'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.Str::random(5),
            'municipality_uuid' => $relleno,
            'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test',
            'tax_regime_uuid' => $relleno,
            'web_page' => 'https://empresa.test',
            'legal_representative_name' => 'Rep',
            'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC',
            'legal_representative_document_number' => '1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function usuario(string $rol, ?string $empresaUuid = null): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prueba '.$rol,
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate($rol, 'api'));

        if ($empresaUuid) {
            DB::table('company_user')->insert([
                'user_id' => $user->id,
                'company_uuid' => $empresaUuid,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user->fresh();
    }

    public function test_un_conductor_no_puede_crear_roles_ni_asignarlos(): void
    {
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('CONDUCTOR', $empresa), ['*']);
        $cab = ['X-Company-UUID' => $empresa];

        $this->postJson('/api/v1/auth/roles', ['name' => 'HACKER'], $cab)->assertForbidden();
        $this->postJson('/api/v1/auth/model-has-roles', [], $cab)->assertForbidden();
        $this->postJson('/api/v1/auth/permissions', [], $cab)->assertForbidden();
        $this->postJson('/api/v1/auth/role-has-permissions', [], $cab)->assertForbidden();
    }

    public function test_un_conductor_no_puede_crear_ni_borrar_usuarios(): void
    {
        $empresa = $this->empresa();
        $objetivo = $this->usuario('EMPLEADO', $empresa);
        Sanctum::actingAs($this->usuario('CONDUCTOR', $empresa), ['*']);
        $cab = ['X-Company-UUID' => $empresa];

        $this->postJson('/api/v1/auth/users', [], $cab)->assertForbidden();
        $this->deleteJson('/api/v1/auth/users/'.$objetivo->uuid, [], $cab)->assertForbidden();
    }

    public function test_superadmin_si_alcanza_la_administracion_de_roles(): void
    {
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('SUPERADMIN', $empresa), ['*']);

        // No se exige un resultado concreto (la validación puede responder 422): lo que importa es que no sea 403.
        $estado = $this->postJson('/api/v1/auth/roles', [], ['X-Company-UUID' => $empresa])->status();
        $this->assertNotSame(403, $estado);
    }

    public function test_admin_empresa_no_puede_darse_el_rol_superadmin(): void
    {
        $empresa = $this->empresa();
        $admin = $this->usuario('ADMIN_EMPRESA', $empresa);
        Role::findOrCreate('SUPERADMIN', 'api');
        Sanctum::actingAs($admin, ['*']);

        $this->putJson('/api/v1/auth/users/'.$admin->uuid, ['roles' => ['SUPERADMIN']], ['X-Company-UUID' => $empresa])
            ->assertForbidden();
        $this->assertFalse($admin->fresh()->hasRole('SUPERADMIN'));
    }

    public function test_admin_empresa_no_puede_modificar_a_un_superadmin(): void
    {
        $empresa = $this->empresa();
        $super = $this->usuario('SUPERADMIN', $empresa);
        Sanctum::actingAs($this->usuario('ADMIN_EMPRESA', $empresa), ['*']);
        $cab = ['X-Company-UUID' => $empresa];

        $this->putJson('/api/v1/auth/users/'.$super->uuid, ['name' => 'Cambiado'], $cab)->assertForbidden();
        $this->deleteJson('/api/v1/auth/users/'.$super->uuid, [], $cab)->assertForbidden();
        $this->patchJson('/api/v1/auth/users/'.$super->uuid.'/toggle-status', [], $cab)->assertForbidden();
    }

    public function test_un_usuario_no_puede_operar_sobre_una_empresa_ajena(): void
    {
        $propia = $this->empresa();
        $ajena = $this->empresa();
        Sanctum::actingAs($this->usuario('EMPLEADO', $propia), ['*']);

        $this->getJson('/api/v1/auth/users/list', ['X-Company-UUID' => $ajena])->assertForbidden();
        $this->getJson('/api/v1/auth/users/list', ['X-Tenant-ID' => $ajena])->assertForbidden();
        $this->assertNotSame(403, $this->getJson('/api/v1/auth/users/list', ['X-Company-UUID' => $propia])->status());
    }

    public function test_superadmin_puede_cambiar_de_empresa(): void
    {
        $ajena = $this->empresa();
        Sanctum::actingAs($this->usuario('SUPERADMIN', $this->empresa()), ['*']);

        $this->assertNotSame(403, $this->getJson('/api/v1/auth/users/list', ['X-Company-UUID' => $ajena])->status());
    }

    public function test_cron_sin_token_correcto_responde_403(): void
    {
        config(['services.cron.secret' => 'secreto-largo']);

        $this->getJson('/api/v1/public/cron/run-all')->assertForbidden();
        $this->getJson('/api/v1/public/cron/run-all?token=incorrecto')->assertForbidden();
        $this->getJson('/api/v1/public/cron/trigger-notifications', ['X-Cron-Token' => 'otro'])->assertForbidden();
    }

    public function test_cron_falla_cerrado_si_no_hay_secreto_configurado(): void
    {
        config(['services.cron.secret' => null]);

        $this->getJson('/api/v1/public/cron/run-all?token=')->assertForbidden();
    }

    public function test_cron_deja_pasar_con_el_token_por_cabecera_o_url(): void
    {
        config(['services.cron.secret' => 'secreto-largo']);
        $middleware = new VerifyCronToken();
        $ok = fn () => response()->json(['ok' => true]);

        $porCabecera = Request::create('/x', 'GET');
        $porCabecera->headers->set('X-Cron-Token', 'secreto-largo');
        $this->assertSame(200, $middleware->handle($porCabecera, $ok)->getStatusCode());

        $porUrl = Request::create('/x?token=secreto-largo', 'GET');
        $this->assertSame(200, $middleware->handle($porUrl, $ok)->getStatusCode());
    }
}
