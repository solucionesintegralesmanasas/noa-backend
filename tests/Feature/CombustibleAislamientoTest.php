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
 * Aislamiento de combustible: un registro solo puede apuntar a un vehículo y
 * un conductor de la empresa (y sede) activa, y no se puede mover a otra.
 *
 * Antes el request solo comprobaba `exists` y el servicio tomaba la empresa del
 * cuerpo: un usuario podía crear o mover registros hacia vehículos ajenos y el
 * endpoint /context devolvía datos de un vehículo de otra empresa.
 */
class CombustibleAislamientoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaA;

    private string $empresaB;

    private string $sedeA1;

    private string $sedeA2;

    private string $sedeB;

    private string $terceroA1;

    private string $vehiculoA1;

    private string $vehiculoA2;

    private string $vehiculoB;

    private string $conductorA;

    private string $conductorB;

    private User $adminA;

    private User $sucursalA1;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
        $this->sedeA1 = $this->crearSede($this->empresaA, 'Sede A1');
        $this->sedeA2 = $this->crearSede($this->empresaA, 'Sede A2');
        $this->sedeB = $this->crearSede($this->empresaB, 'Sede B');

        $this->terceroA1 = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_number' => '91000001',
            'first_name' => 'TERCERO',
            'last_name' => 'A1',
            'email' => 'tercero.a1@test.local',
            'branch_uuid' => $this->sedeA1,
        ]);
        $terceroA2 = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_number' => '91000002',
            'first_name' => 'TERCERO',
            'last_name' => 'A2',
            'email' => 'tercero.a2@test.local',
            'branch_uuid' => $this->sedeA2,
        ]);
        $terceroB = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaB,
            'person_type' => 'NATURAL',
            'document_number' => '92000001',
            'first_name' => 'TERCERO',
            'last_name' => 'B',
            'email' => 'tercero.b@test.local',
            'branch_uuid' => $this->sedeB,
        ]);

        $this->vehiculoA1 = $this->crearVehiculo($this->empresaA, $this->terceroA1, $this->sedeA1, 'FUEL01');
        $this->vehiculoA2 = $this->crearVehiculo($this->empresaA, $terceroA2, $this->sedeA2, 'FUEL02');
        $this->vehiculoB = $this->crearVehiculo($this->empresaB, $terceroB, $this->sedeB, 'FUEL03');

        $this->conductorA = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_number' => '91000003',
            'first_name' => 'CONDUCTOR',
            'last_name' => 'A',
            'email' => 'conductor.a@test.local',
            'branch_uuid' => $this->sedeA1,
            'is_driver' => 1,
        ]);
        $this->conductorB = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaB,
            'person_type' => 'NATURAL',
            'document_number' => '92000002',
            'first_name' => 'CONDUCTOR',
            'last_name' => 'B',
            'email' => 'conductor.b@test.local',
            'branch_uuid' => $this->sedeB,
            'is_driver' => 1,
        ]);

        $this->adminA = $this->crearUsuario($this->empresaA, 'ADMIN_EMPRESA', 'admin.fuel@test.local', null);
        $this->sucursalA1 = $this->crearUsuario($this->empresaA, 'SUCURSAL', 'sucursal.fuel@test.local', $this->terceroA1);
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

    private function crearUsuario(string $empresa, string $rol, string $email, ?string $tercero): User
    {
        // Las rutas de combustible llevan `permission:` explícito: el rol debe
        // tener los permisos para pasar el middleware (SUCURSAL no elimina).
        $rolInstancia = Role::findOrCreate($rol, 'api');
        foreach (['index', 'create', 'update'] as $accion) {
            $rolInstancia->givePermissionTo(Permission::findOrCreate("fuel_logs.{$accion}", 'api'));
        }
        if ($rol === 'ADMIN_EMPRESA') {
            $rolInstancia->givePermissionTo(Permission::findOrCreate('fuel_logs.delete', 'api'));
        }

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
            'third_party_uuid' => $tercero,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function comoAdminA()
    {
        Sanctum::actingAs($this->adminA, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresaA]);
    }

    private function comoSucursalA1()
    {
        Sanctum::actingAs($this->sucursalA1, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresaA]);
    }

    /** @return array<string, mixed> */
    private function datos(string $vehiculo, ?string $conductor = null, string $fecha = '2026-10-01'): array
    {
        return [
            'vehicle_uuid' => $vehiculo,
            'driver_uuid' => $conductor,
            'date' => $fecha,
            'total_spent' => 150000,
            'fuel_price_per_gallon' => 15000,
        ];
    }

    public function test_no_crea_un_registro_con_un_vehiculo_de_otra_empresa(): void
    {
        $this->comoAdminA()
            ->postJson('/api/v1/fleet-management/fuel-logs', $this->datos($this->vehiculoB))
            ->assertStatus(422);

        $this->assertSame(0, DB::table('fuel_logs')->count());
    }

    public function test_no_crea_un_registro_con_un_conductor_de_otra_empresa(): void
    {
        $this->comoAdminA()
            ->postJson('/api/v1/fleet-management/fuel-logs', $this->datos($this->vehiculoA1, $this->conductorB))
            ->assertStatus(422);

        $this->assertSame(0, DB::table('fuel_logs')->count());
    }

    public function test_el_contexto_de_un_vehiculo_ajeno_falla_cerrado(): void
    {
        $this->comoAdminA()
            ->getJson('/api/v1/fleet-management/fuel-logs/context?vehicle_uuid='.$this->vehiculoB)
            ->assertStatus(422);
    }

    public function test_el_sucursal_no_crea_combustible_para_un_vehiculo_de_otra_sede(): void
    {
        $this->comoSucursalA1()
            ->postJson('/api/v1/fleet-management/fuel-logs', $this->datos($this->vehiculoA2))
            ->assertStatus(422);

        $this->assertSame(0, DB::table('fuel_logs')->count());
    }

    public function test_el_sucursal_solo_lista_y_abre_los_registros_de_su_sede(): void
    {
        $this->comoSucursalA1()
            ->postJson('/api/v1/fleet-management/fuel-logs', $this->datos($this->vehiculoA1, $this->conductorA))
            ->assertCreated()
            ->assertJsonPath('data.vehicle_uuid', $this->vehiculoA1);

        $registroA2 = $this->insertar('fuel_logs', [
            'company_uuid' => $this->empresaA,
            'vehicle_uuid' => $this->vehiculoA2,
            'date' => '2026-10-02',
            'total_spent' => 0,
            'fuel_price_per_gallon' => 0,
        ]);

        $this->comoSucursalA1()
            ->getJson('/api/v1/fleet-management/fuel-logs?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.vehicle_uuid', $this->vehiculoA1);

        $this->comoSucursalA1()
            ->getJson('/api/v1/fleet-management/fuel-logs/'.$registroA2)
            ->assertNotFound();
    }

    public function test_no_puede_mover_un_registro_a_un_vehiculo_de_otra_empresa(): void
    {
        $creado = $this->comoAdminA()
            ->postJson('/api/v1/fleet-management/fuel-logs', $this->datos($this->vehiculoA1))
            ->assertCreated()
            ->json('data');

        $this->comoAdminA()
            ->putJson('/api/v1/fleet-management/fuel-logs/'.$creado['uuid'], [
                'vehicle_uuid' => $this->vehiculoB,
            ])
            ->assertStatus(422);

        $this->assertSame(
            $this->vehiculoA1,
            DB::table('fuel_logs')->where('uuid', $creado['uuid'])->value('vehicle_uuid'),
        );
    }

    public function test_al_mover_un_registro_recalcula_la_cadena_del_vehiculo_anterior(): void
    {
        // Tres registros en A1: 10, 20 y 5 galones.
        foreach ([
            ['2026-10-01', 150000],
            ['2026-10-02', 300000],
            ['2026-10-03', 75000],
        ] as [$fecha, $gasto]) {
            $this->comoAdminA()
                ->postJson('/api/v1/fleet-management/fuel-logs', [
                    'vehicle_uuid' => $this->vehiculoA1,
                    'date' => $fecha,
                    'total_spent' => $gasto,
                    'fuel_price_per_gallon' => 15000,
                ])
                ->assertCreated();
        }

        $segundo = DB::table('fuel_logs')
            ->where('vehicle_uuid', $this->vehiculoA1)
            ->where('date', '2026-10-02')
            ->first();
        $tercero = DB::table('fuel_logs')
            ->where('vehicle_uuid', $this->vehiculoA1)
            ->where('date', '2026-10-03')
            ->first();

        // Antes de mover: el tercero hereda el saldo del segundo (30 galones).
        $this->assertSame(30.0, (float) $tercero->initial_balance);

        $this->comoAdminA()
            ->putJson('/api/v1/fleet-management/fuel-logs/'.$segundo->uuid, [
                'vehicle_uuid' => $this->vehiculoA2,
            ])
            ->assertOk();

        // Al salir el segundo de la cadena, el tercero debe heredar los 10 del primero.
        $terceroRecalculado = DB::table('fuel_logs')->where('uuid', $tercero->uuid)->first();
        $this->assertSame(10.0, (float) $terceroRecalculado->initial_balance);

        // Y el segundo, ya en A2, arranca su propia cadena desde cero.
        $segundoEnA2 = DB::table('fuel_logs')->where('uuid', $segundo->uuid)->first();
        $this->assertSame($this->vehiculoA2, $segundoEnA2->vehicle_uuid);
        $this->assertSame(0.0, (float) $segundoEnA2->initial_balance);
    }
}
