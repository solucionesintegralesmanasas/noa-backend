<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\ResourceAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase C: autorización por recurso (middleware `authz`, config/authorization.php).
 */
class AutorizacionPorRecursoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ResourceAuthorization::olvidar();
        foreach (['vehicles.index', 'vehicles.create', 'vehicles.update', 'vehicles.delete', 'bank_details.create', 'fuec.index', 'rup_records.index'] as $p) {
            Permission::findOrCreate($p, 'api');
        }
    }

    private function empresa(): string
    {
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

        return $uuid;
    }

    /** Usuario con un rol y, opcionalmente, permisos concedidos directamente. */
    private function usuario(string $rol, string $empresa, array $permisos = []): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate($rol, 'api'));
        foreach ($permisos as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'api'));
        }
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $empresa, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function cab(string $empresa): array
    {
        return ['X-Company-UUID' => $empresa];
    }

    public function test_el_nombre_de_la_ruta_se_traduce_al_permiso_correcto(): void
    {
        $r = new ResourceAuthorization();

        $this->assertSame(['vehicles.index', 'vehicles.profile', 'vehicles.view', 'vehicles.show'], $r->resolver('api.v1.fleet.vehicles.index', 'GET')['permisos']);
        $this->assertSame(['vehicles.create'], $r->resolver('api.v1.fleet.vehicles.store', 'POST')['permisos']);
        $this->assertSame(['vehicles.update'], $r->resolver('api.v1.fleet.vehicles.update', 'PUT')['permisos']);
        $this->assertSame(['vehicles.delete'], $r->resolver('api.v1.fleet.vehicles.destroy', 'DELETE')['permisos']);
        $this->assertSame(['bank_details.create'], $r->resolver('api.v1.administration.bank-details.store', 'POST')['permisos']);
        $this->assertSame('fuec', $r->resolver('api.v1.contract-extractions.fuecs.index', 'GET')['recurso']);
        $this->assertSame('rup_records', $r->resolver('api.v1.administration.rup.index', 'GET')['recurso']);
    }

    public function test_catalogos_leen_todos_y_escriben_solo_superadmin(): void
    {
        $r = new ResourceAuthorization();

        $this->assertNull($r->resolver('api.v1.catalogs.brands.index', 'GET'));
        $this->assertSame(['SUPERADMIN'], $r->resolver('api.v1.catalogs.brands.store', 'POST')['roles']);
    }

    public function test_rutas_exentas_o_sin_mapa_no_se_evaluan(): void
    {
        $r = new ResourceAuthorization();

        $this->assertNull($r->resolver('api.v1.dashboard.summary', 'GET'));
        $this->assertNull($r->resolver('api.v1.tracking.alerts.index', 'GET'));
        $this->assertNull($r->resolver('api.v1.recurso-que-no-existe.index', 'GET'));
        $this->assertNull($r->resolver(null, 'GET'));
    }

    public function test_enforce_bloquea_sin_el_permiso_y_deja_pasar_con_el_permiso(): void
    {
        config(['authorization.enforce' => true]);
        $empresa = $this->empresa();

        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa), ['*']);
        $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa))->assertForbidden();

        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa, ['vehicles.index']), ['*']);
        $this->assertNotSame(403, $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa))->status());
    }

    public function test_enforce_distingue_leer_de_escribir(): void
    {
        config(['authorization.enforce' => true]);
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa, ['vehicles.index']), ['*']);

        $this->assertNotSame(403, $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa))->status());
        $this->postJson('/api/v1/fleet-management/vehicles', [], $this->cab($empresa))->assertForbidden();
        $this->deleteJson('/api/v1/fleet-management/vehicles/'.Str::uuid(), [], $this->cab($empresa))->assertForbidden();
    }

    public function test_superadmin_pasa_siempre(): void
    {
        config(['authorization.enforce' => true]);
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('SUPERADMIN', $empresa), ['*']);

        $this->assertNotSame(403, $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa))->status());
        $this->assertNotSame(403, $this->postJson('/api/v1/catalogs/brands', [], $this->cab($empresa))->status());
    }

    public function test_en_catalogos_escribir_exige_superadmin_pero_leer_no(): void
    {
        config(['authorization.enforce' => true]);
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('ADMIN_EMPRESA', $empresa), ['*']);

        $this->postJson('/api/v1/catalogs/brands', [], $this->cab($empresa))->assertForbidden();
        $this->assertNotSame(403, $this->getJson('/api/v1/catalogs/brands', $this->cab($empresa))->status());
    }

    public function test_en_modo_auditoria_no_bloquea_pero_deja_constancia(): void
    {
        config(['authorization.enforce' => false]);
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa), ['*']);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $m, array $c) => str_contains($m, 'auditoría') && $c['exige'] === ['vehicles.index', 'vehicles.profile', 'vehicles.view', 'vehicles.show']);

        $this->assertNotSame(403, $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa))->status());
    }

    public function test_la_auditoria_registra_una_sola_vez_por_usuario_y_ruta(): void
    {
        config(['authorization.enforce' => false]);
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa), ['*']);

        Log::shouldReceive('warning')->once();

        $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa));
        $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa));
    }

    public function test_el_rechazo_usa_el_formato_de_error_del_proyecto(): void
    {
        config(['authorization.enforce' => true]);
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa), ['*']);

        $this->getJson('/api/v1/fleet-management/vehicles', $this->cab($empresa))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.http_status', 403);
    }

    public function test_el_modo_por_defecto_es_auditoria(): void
    {
        $this->assertFalse(config('authorization.enforce'));
    }

    public function test_las_rutas_con_permiso_propio_no_se_evaluan_dos_veces(): void
    {
        config(['authorization.enforce' => true]);
        $empresa = $this->empresa();
        // /tracking/* ya exige permission:locations.*; sin ese permiso responde el middleware propio (403 de Spatie)
        // y con él no interviene authz.
        Permission::findOrCreate('locations.view', 'api');
        Sanctum::actingAs($this->usuario('EMPLEADO', $empresa, ['locations.view']), ['*']);

        $this->assertNotSame(403, $this->getJson('/api/v1/tracking/active-drivers', $this->cab($empresa))->status());
    }

    public function test_el_comando_de_reporte_funciona_y_lista_rutas_evaluadas(): void
    {
        $this->artisan('authz:report', ['--sin-mapa' => true])
            ->expectsOutputToContain('modo: auditoría')
            ->assertSuccessful();
    }
}
