<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessCollaborationAgreement;
use App\Models\DriverLicense;
use App\Models\Notification;
use App\Models\OperationCard;
use App\Models\Project;
use App\Models\ProjectDriverVehicle;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\ActorScope\AlcanceDelActor;
use App\Services\ActorScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * SPEC-013: Pruebas del servicio unificado de Alcance del Actor (AlcanceDelActor / ActorScopeService).
 *
 * Valida:
 * 1. Presupuesto de consultas: resolución de contexto en exactamente 1 consulta SQL.
 * 2. Comportamiento y aislamiento por rol: SUPERADMIN, ADMIN_EMPRESA, AFILIADO, CONDUCTOR, AFILIADO+CONDUCTOR.
 * 3. Falla cerrada (Fail-Closed) ante falta de contexto o usuario sin empresa.
 * 4. Memorización de consultas de asignaciones en la misma petición.
 * 5. Predicados SQL: scopeVehicles, scopeDrivers, scopeNotifications, scopeTracking.
 */
class AlcanceDelActorTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresaA;

    private string $empresaB;

    private string $terceroAfiliado;

    private string $terceroConductor;

    private string $terceroCombinado;

    private string $vehiculoAfiliado;

    private string $vehiculoConductor;

    private string $vehiculoEmpresaB;

    private User $userSuperadmin;

    private User $userAdminEmpresa;

    private User $userAfiliado;

    private User $userConductor;

    private User $userCombinado;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa('Transportes A SAS');
        $this->empresaB = $this->crearEmpresa('Transportes B SAS');

        // Terceros
        $this->terceroAfiliado = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'JURIDICA',
            'document_number' => '900100100',
            'company_name' => 'AFILIADO A SAS',
            'is_affiliate' => 1,
            'is_active' => 1,
        ]);

        $this->terceroConductor = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_number' => '100200300',
            'first_name' => 'CARLOS',
            'last_name' => 'CONDUCTOR',
            'is_driver' => 1,
            'is_active' => 1,
        ]);

        $this->terceroCombinado = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_number' => '100300400',
            'first_name' => 'PEDRO',
            'last_name' => 'COMBINADO',
            'is_affiliate' => 1,
            'is_driver' => 1,
            'is_active' => 1,
        ]);

        // Vehículos
        $this->vehiculoAfiliado = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaA,
            'third_party_uuid' => $this->terceroAfiliado,
            'vehicle_license_plate' => 'AFI111',
            'is_active' => 1,
        ]);

        $this->vehiculoConductor = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaA,
            'third_party_uuid' => null,
            'vehicle_license_plate' => 'CND222',
            'is_active' => 1,
        ]);

        $this->vehiculoEmpresaB = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaB,
            'third_party_uuid' => null,
            'vehicle_license_plate' => 'EMB333',
            'is_active' => 1,
        ]);

        // Asignación de proyecto al conductor
        $proyecto = $this->insertar('projects', [
            'company_uuid' => $this->empresaA,
            'project_name' => 'Ruta Cali',
            'start_date' => '2026-01-01',
            'completion_date' => '2026-12-31',
        ]);

        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $proyecto,
            'third_party_uuid' => $this->terceroConductor,
            'vehicle_uuid' => $this->vehiculoConductor,
            'is_active' => 1,
        ]);

        // Usuarios
        $this->userSuperadmin = $this->crearUsuario('SUPERADMIN', null, null);
        $this->userAdminEmpresa = $this->crearUsuario('ADMIN_EMPRESA', $this->empresaA, null);
        $this->userAfiliado = $this->crearUsuario('AFILIADO', $this->empresaA, $this->terceroAfiliado);
        $this->userConductor = $this->crearUsuario('CONDUCTOR', $this->empresaA, $this->terceroConductor);

        $this->userCombinado = $this->crearUsuario('AFILIADO', $this->empresaA, $this->terceroCombinado);
        $this->userCombinado->assignRole(Role::findOrCreate('CONDUCTOR', 'api'));
        $this->userCombinado = $this->userCombinado->fresh();
    }

    private function crearEmpresa(string $nombre): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid,
            'person_type' => 'PERSONA JURIDICA',
            'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno,
            'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => $nombre,
            'municipality_uuid' => $relleno,
            'address' => 'Avenida Principal',
            'email' => Str::random(8).'@empresa.test',
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

    private function crearUsuario(string $rol, ?string $empresa, ?string $tercero): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.$rol,
            'email' => Str::random(8).'@noa.test',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole(Role::findOrCreate($rol, 'api'));

        if ($empresa) {
            DB::table('company_user')->insert([
                'user_id' => $user->id,
                'company_uuid' => $empresa,
                'third_party_uuid' => $tercero,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user->fresh();
    }

    // ── Tests de Presupuesto y Funcionamiento Central ────────────────────────────

    public function test_resolucion_de_contexto_consume_exactamente_una_consulta(): void
    {
        $this->userAfiliado->load('roles');
        Sanctum::actingAs($this->userAfiliado, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->limpiarCache();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $actorScope->resolverContexto($this->userAfiliado, $this->empresaA);

        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries, 'La resolución del contexto debe ejecutar exactamente 1 consulta SQL.');

        // Lecturas posteriores sobre la misma instancia resuelta consumen 0 consultas SQL
        DB::flushQueryLog();

        $empresa = $actorScope->companyUuid();
        $tercero = $actorScope->thirdPartyUuid();
        $esAfiliado = $actorScope->esAfiliado();
        $roles = $actorScope->contexto()['roles'];

        $this->assertSame($this->empresaA, $empresa);
        $this->assertSame($this->terceroAfiliado, $tercero);
        $this->assertTrue($esAfiliado);
        $this->assertContains('AFILIADO', $roles);

        $queriesSegundoPaso = DB::getQueryLog();
        $this->assertCount(0, $queriesSegundoPaso, 'Las lecturas posteriores del contexto deben estar memorizadas en memoria.');
    }

    public function test_superadmin_tiene_acceso_global_o_a_empresa_elegida(): void
    {
        $this->userSuperadmin->load('roles');
        Sanctum::actingAs($this->userSuperadmin, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->resolverContexto($this->userSuperadmin, null);

        // Sin empresa especificada ve todos los vehículos
        $qGlobal = Vehicle::withoutGlobalScopes();
        $actorScope->aplicarAVehiculos($qGlobal);
        $this->assertContains($this->vehiculoAfiliado, $qGlobal->pluck('uuid')->all());
        $this->assertContains($this->vehiculoEmpresaB, $qGlobal->pluck('uuid')->all());

        // Con empresa especificada se acota a dicha empresa
        $actorScope->limpiarCache();
        $actorScope->resolverContexto($this->userSuperadmin, $this->empresaA);
        $qEmpresaA = Vehicle::withoutGlobalScopes();
        $actorScope->aplicarAVehiculos($qEmpresaA, $this->empresaA);
        $uuidsA = $qEmpresaA->pluck('uuid')->all();
        $this->assertContains($this->vehiculoAfiliado, $uuidsA);
        $this->assertNotContains($this->vehiculoEmpresaB, $uuidsA);
    }

    public function test_admin_empresa_aislado_estrictamente_a_su_empresa(): void
    {
        Sanctum::actingAs($this->userAdminEmpresa, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->resolverContexto($this->userAdminEmpresa, $this->empresaA);

        $q = Vehicle::query();
        $actorScope->aplicarAVehiculos($q);
        $uuids = $q->pluck('uuid')->all();

        $this->assertContains($this->vehiculoAfiliado, $uuids);
        $this->assertContains($this->vehiculoConductor, $uuids);
        $this->assertNotContains($this->vehiculoEmpresaB, $uuids, 'No debe ver vehículos de otra empresa.');
    }

    public function test_afiliado_aislado_estrictamente_a_sus_vehiculos(): void
    {
        Sanctum::actingAs($this->userAfiliado, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->resolverContexto($this->userAfiliado, $this->empresaA);

        $q = Vehicle::query();
        $actorScope->aplicarAVehiculos($q);
        $uuids = $q->pluck('uuid')->all();

        $this->assertContains($this->vehiculoAfiliado, $uuids, 'Debe ver su vehículo asignado.');
        $this->assertNotContains($this->vehiculoConductor, $uuids, 'No debe ver vehículos de otros en la misma empresa.');
        $this->assertNotContains($this->vehiculoEmpresaB, $uuids, 'No debe ver vehículos de otra empresa.');
    }

    public function test_conductor_puro_aislado_a_sus_vehiculos_asignados(): void
    {
        Sanctum::actingAs($this->userConductor, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->resolverContexto($this->userConductor, $this->empresaA);

        $q = Vehicle::query();
        $actorScope->aplicarAVehiculos($q);
        $uuids = $q->pluck('uuid')->all();

        $this->assertContains($this->vehiculoConductor, $uuids, 'Debe ver el vehículo asignado en proyecto.');
        $this->assertNotContains($this->vehiculoAfiliado, $uuids, 'No debe ver vehículos ajenos.');
    }

    public function test_rol_combinado_afiliado_y_conductor_prevalece_regla_afiliado(): void
    {
        Sanctum::actingAs($this->userCombinado, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->resolverContexto($this->userCombinado, $this->empresaA);

        $q = Vehicle::query();
        $actorScope->aplicarAVehiculos($q);
        $uuids = $q->pluck('uuid')->all();

        // El usuario combinado no tiene vehículos asignados como afiliado todavía -> ve lista vacía
        $this->assertNotContains($this->vehiculoAfiliado, $uuids);
        $this->assertNotContains($this->vehiculoConductor, $uuids, 'No debe ver flota global por la rama de conductor.');
    }

    public function test_falla_cerrada_ante_contexto_invalido_o_sin_usuario(): void
    {
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->limpiarCache();

        // Sin usuario autenticado: los scopes agregan WHERE 1 = 0 garantizando cero exposición
        $qVehiculos = Vehicle::withoutGlobalScopes();
        $actorScope->aplicarAVehiculos($qVehiculos);
        $this->assertEmpty($qVehiculos->get());

        $qConductores = ThirdParty::withoutGlobalScopes();
        $actorScope->aplicarAConductores($qConductores);
        $this->assertEmpty($qConductores->get());

        $qNotificaciones = Notification::withoutGlobalScopes();
        $actorScope->aplicarANotificaciones($qNotificaciones);
        $this->assertEmpty($qNotificaciones->get());
    }

    public function test_memorizacion_de_asignaciones_no_repite_consultas_en_la_misma_peticion(): void
    {
        Sanctum::actingAs($this->userAfiliado, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->resolverContexto($this->userAfiliado, $this->empresaA);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $primera = $actorScope->obtenerVehiculosDeAfiliado();
        $queries1 = DB::getQueryLog();
        $this->assertCount(1, $queries1);

        DB::flushQueryLog();
        for ($i = 0; $i < 5; $i++) {
            $segunda = $actorScope->obtenerVehiculosDeAfiliado();
            $this->assertSame($primera, $segunda);
        }
        $queriesRepetidas = DB::getQueryLog();
        $this->assertCount(0, $queriesRepetidas, 'Consultas subsiguientes de asignaciones deben provenir de memoria.');
    }

    public function test_alias_y_resolucion_en_contenedor(): void
    {
        $instancia1 = app(AlcanceDelActor::class);
        $instancia2 = app(ActorScopeService::class);
        $instancia3 = app('actor.scope');

        $this->assertInstanceOf(AlcanceDelActor::class, $instancia1);
        $this->assertInstanceOf(AlcanceDelActor::class, $instancia2);
        $this->assertInstanceOf(AlcanceDelActor::class, $instancia3);
    }

    public function test_intento_de_bypass_cross_company_falla_cerrado(): void
    {
        Sanctum::actingAs($this->userAfiliado, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->limpiarCache();

        // El usuario pertenece a empresaA, pero intenta solicitar empresaB
        $actorScope->resolverContexto($this->userAfiliado, $this->empresaB);

        $this->assertNull($actorScope->companyUuid(), 'No debe conceder companyUuid si no pertenece a ella.');

        $qVehiculos = Vehicle::withoutGlobalScopes();
        $actorScope->aplicarAVehiculos($qVehiculos);
        $this->assertEmpty($qVehiculos->get(), 'Debe fallar cerrado con WHERE 1 = 0.');

        $qConductores = ThirdParty::withoutGlobalScopes();
        $actorScope->aplicarAConductores($qConductores);
        $this->assertEmpty($qConductores->get(), 'Conductores debe fallar cerrado con WHERE 1 = 0.');

        $qNotificaciones = Notification::withoutGlobalScopes();
        $actorScope->aplicarANotificaciones($qNotificaciones);
        $this->assertEmpty($qNotificaciones->get(), 'Notificaciones debe fallar cerrado con WHERE 1 = 0.');
    }

    public function test_admin_empresa_con_rol_afiliado_mantiene_vision_global_de_la_empresa(): void
    {
        // Asignar rol AFILIADO al usuario ADMIN_EMPRESA
        $this->userAdminEmpresa->assignRole('AFILIADO');
        $this->userAdminEmpresa->load('roles');

        Sanctum::actingAs($this->userAdminEmpresa, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->limpiarCache();
        $actorScope->resolverContexto($this->userAdminEmpresa, $this->empresaA);

        $q = Vehicle::query();
        $actorScope->aplicarAVehiculos($q);
        $uuids = $q->pluck('uuid')->all();

        // Como administrador de empresa, debe ver todos los vehículos de su empresa
        $this->assertContains($this->vehiculoAfiliado, $uuids);
        $this->assertContains($this->vehiculoConductor, $uuids);
        $this->assertNotContains($this->vehiculoEmpresaB, $uuids);
    }

    public function test_scope_drivers_y_tracking_para_conductor_puro(): void
    {
        Sanctum::actingAs($this->userConductor, ['*']);
        $actorScope = app(AlcanceDelActor::class);
        $actorScope->limpiarCache();
        $actorScope->resolverContexto($this->userConductor, $this->empresaA);

        // scopeDrivers solo permite su propio tercero
        $qConductores = ThirdParty::query();
        $actorScope->scopeDrivers($qConductores);
        $conductorUuids = $qConductores->pluck('uuid')->all();

        $this->assertContains($this->terceroConductor, $conductorUuids);
        $this->assertNotContains($this->terceroAfiliado, $conductorUuids);

        // scopeTracking solo permite su propio tercero
        $alcanceRastreo = $actorScope->obtenerAlcanceRastreo();
        $this->assertNotNull($alcanceRastreo);
        $this->assertSame([$this->terceroConductor], $alcanceRastreo['conductores']);
        $this->assertTrue($actorScope->permiteConductor($this->terceroConductor));
        $this->assertFalse($actorScope->permiteConductor($this->terceroAfiliado));
    }

    public function test_integracion_http_listado_vehiculos_con_aislamiento_de_afiliado(): void
    {
        Sanctum::actingAs($this->userAfiliado, ['*']);

        $response = $this->withHeaders([
            'X-Company-UUID' => $this->empresaA,
        ])->getJson('/api/v1/fleet-management/vehicles');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $items = isset($data['data']) ? $data['data'] : (is_array($data) ? $data : []);

        $uuids = collect($items)->pluck('uuid')->all();
        $this->assertContains($this->vehiculoAfiliado, $uuids);
        $this->assertNotContains($this->vehiculoConductor, $uuids);
        $this->assertNotContains($this->vehiculoEmpresaB, $uuids);
    }
}
