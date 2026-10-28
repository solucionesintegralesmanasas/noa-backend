<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fleet\FuelRequestService;
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
 * Aislamiento de solicitudes de tanqueo (commit `fix(fleet)`).
 *
 * Antes: el lookup del vehículo iba con `withoutGlobalScopes`, la empresa
 * caía a la del vehículo cuando no había contexto (falla abierta), el
 * `when()` de `bloquear()` dejaba aprobar solicitudes ajenas desde flujos
 * sin contexto y `expirarVencidas()` sin empresa vencía TODAS las empresas.
 * El store del controlador solo comprobaba `exists` pelado.
 */
class SolicitudTanqueoAislamientoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaA;

    private string $empresaB;

    private string $sedeA1;

    private string $sedeB;

    private string $terceroA1;

    private string $terceroB;

    private string $vehiculoA1;

    private string $vehiculoB;

    private User $adminA;

    private User $adminB;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
        $this->sedeA1 = $this->crearSede($this->empresaA, 'Sede A1');
        $this->sedeB = $this->crearSede($this->empresaB, 'Sede B');

        $this->terceroA1 = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_number' => '93000001',
            'first_name' => 'TERCERO',
            'last_name' => 'A1',
            'email' => 'solicitud.a1@test.local',
            'branch_uuid' => $this->sedeA1,
        ]);
        $this->terceroB = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaB,
            'person_type' => 'NATURAL',
            'document_number' => '93000002',
            'first_name' => 'TERCERO',
            'last_name' => 'B',
            'email' => 'solicitud.b@test.local',
            'branch_uuid' => $this->sedeB,
        ]);

        $this->vehiculoA1 = $this->crearVehiculo($this->empresaA, $this->terceroA1, $this->sedeA1, 'SOLIC01');
        $this->vehiculoB = $this->crearVehiculo($this->empresaB, $this->terceroB, $this->sedeB, 'SOLIC02');

        $this->adminA = $this->crearUsuario($this->empresaA, 'ADMIN_EMPRESA', 'admin.solicitud.a@test.local');
        $this->adminB = $this->crearUsuario($this->empresaB, 'ADMIN_EMPRESA', 'admin.solicitud.b@test.local');
        $this->superadmin = $this->crearSuperadmin('super.solicitud@test.local');
    }

    public function test_no_crea_solicitud_con_un_vehiculo_de_otra_empresa(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/fuel-requests', [
                'vehicle_uuid' => $this->vehiculoB,
                'notes' => 'Intento de prueba',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El vehículo no pertenece a la empresa o sede activa.']);

        $this->assertSame(0, DB::table('fuel_requests')->count());
    }

    public function test_la_creacion_falla_cerrado_sin_empresa_activa(): void
    {
        // Llamada directa al servicio sin petición ni contexto (como un flujo
        // interno): antes caía a la empresa del vehículo y creaba la
        // solicitud en cualquier empresa. Ahora exige empresa.
        $excepcion = null;

        try {
            app(FuelRequestService::class)->crear($this->vehiculoA1);
        } catch (\InvalidArgumentException $e) {
            $excepcion = $e->getMessage();
        }

        $this->assertSame('No hay una empresa activa para crear la solicitud.', $excepcion);
        $this->assertSame(0, DB::table('fuel_requests')->count());
    }

    public function test_el_admin_de_otra_empresa_no_aprueba_la_solicitud(): void
    {
        $solicitud = $this->sembrarSolicitud($this->empresaB, $this->vehiculoB, 'PENDIENTE');

        $this->como($this->adminA, $this->empresaA)
            ->postJson("/api/v1/fleet-management/fuel-requests/{$solicitud}/approve")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'La solicitud de tanqueo no existe.']);

        $this->assertSame('PENDIENTE', DB::table('fuel_requests')->where('uuid', $solicitud)->value('status'));
    }

    public function test_el_superadmin_puede_aprobar_sin_empresa_activa(): void
    {
        $solicitud = $this->sembrarSolicitud($this->empresaA, $this->vehiculoA1, 'PENDIENTE');

        // SUPERADMIN opera en cualquier empresa; sin empresa activa debe poder
        // aprobar (la salvaguarda bloquea a los demás roles, no a él).
        Sanctum::actingAs($this->superadmin, ['*']);

        $this->postJson("/api/v1/fleet-management/fuel-requests/{$solicitud}/approve")
            ->assertStatus(200);

        $this->assertSame('APROBADA', DB::table('fuel_requests')->where('uuid', $solicitud)->value('status'));
    }

    public function test_expirar_vencidas_no_toca_otras_empresas(): void
    {
        $solicitud = $this->sembrarSolicitud($this->empresaB, $this->vehiculoB, 'APROBADA', true);

        // El listado del admin A usa la empresa del contexto (A): la solicitud
        // vencida de B no se toca. Antes expiraba TODAS las empresas.
        $this->como($this->adminA, $this->empresaA)
            ->getJson('/api/v1/fleet-management/fuel-requests')
            ->assertStatus(200);

        $this->assertSame('APROBADA', DB::table('fuel_requests')->where('uuid', $solicitud)->value('status'));

        // Con la empresa correcta (B) sí se vence.
        $this->como($this->adminB, $this->empresaB)
            ->getJson('/api/v1/fleet-management/fuel-requests')
            ->assertStatus(200);

        $this->assertSame('VENCIDA', DB::table('fuel_requests')->where('uuid', $solicitud)->value('status'));
    }

    // ─── Ayudantes ─────────────────────────────────────────────────────────

    private function como(User $usuario, string $empresaCompania)
    {
        Sanctum::actingAs($usuario, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $empresaCompania]);
    }

    /**
     * Siembra una solicitud directamente (el flujo HTTP de creación ya se
     * valida en los otros casos).
     */
    private function sembrarSolicitud(string $empresa, string $vehiculo, string $estado, bool $vencida = false): string
    {
        $uuid = (string) Str::uuid();

        DB::table('fuel_requests')->insert([
            'uuid' => $uuid,
            'company_uuid' => $empresa,
            'vehicle_uuid' => $vehiculo,
            'code' => 'S-'.$this->aleatorio(),
            'public_token' => Str::random(48),
            'status' => $estado,
            'requested_at' => now()->subHours(30),
            'approved_at' => $estado === 'APROBADA' ? now()->subHours(25) : null,
            'approved_by_name' => $estado === 'APROBADA' ? 'Aprobador B' : null,
            'expires_at' => $estado === 'APROBADA'
                ? ($vencida ? now()->subHours(1) : now()->addHours(24))
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $uuid;
    }

    private function crearEmpresa(): string
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

    private function crearSede(string $empresa, string $nombre): string
    {
        $ciudad = $this->insertar('cities', ['name' => 'Ciudad '.$nombre]);

        return $this->insertar('branches', [
            'company_uuid' => $empresa,
            'name' => $nombre,
            'address' => 'Calle '.$nombre,
            'municipality_uuid' => $ciudad,
            'status' => 1,
        ]);
    }

    private function crearVehiculo(string $empresa, string $tercero, string $sede, string $placa): string
    {
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $empresa,
            'third_party_uuid' => $tercero,
            'vehicle_license_plate' => $placa,
            'fuel_type' => 'DIESEL',
            'engine_displacement' => '3000',
            'is_active' => 1,
        ]);

        $this->insertar('vehicles_branches', [
            'company_uuid' => $empresa,
            'vehicle_uuid' => $vehiculo,
            'branch_uuid' => $sede,
            'entry_date' => '2026-01-01',
            'exit_date' => '2026-01-01',
            'exit_type' => 'ENTRADA',
            'is_active' => 1,
        ]);

        return $vehiculo;
    }

    private function crearUsuario(string $empresa, string $rol, string $email): User
    {
        // Las rutas de aprobar/rechazar llevan `permission:` explícito.
        $rolInstancia = Role::findOrCreate($rol, 'api');
        $rolInstancia->givePermissionTo(Permission::findOrCreate('fuel_requests.approve', 'api'));
        $rolInstancia->givePermissionTo(Permission::findOrCreate('fuel_requests.reject', 'api'));

        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole($rolInstancia);
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function crearSuperadmin(string $email): User
    {
        // Sin empresas asociadas: el contexto no resuelve ninguna, que es la
        // única vía que ejerce la salvaguarda de SUPERADMIN.
        $rolInstancia = Role::findOrCreate('SUPERADMIN', 'api');
        $rolInstancia->givePermissionTo(Permission::findOrCreate('fuel_requests.approve', 'api'));

        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole($rolInstancia);

        return $user->fresh();
    }

    private function aleatorio(): string
    {
        return (string) random_int(100000, 999999);
    }
}
