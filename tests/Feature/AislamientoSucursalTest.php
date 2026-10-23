<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ThirdParty;
use App\Models\User;
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
 * Aislamiento por sede: el rol SUCURSAL solo ve los registros de la sede de su
 * tercero vinculado. Aplica a vehículos, terceros y módulos que dependen de
 * ellos (documentos, tarjetas, mantenimientos, etc.). Los administradores ven
 * toda la empresa.
 */
class AislamientoSucursalTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $sucursalA;

    private string $sucursalB;

    private string $terceroA;

    private string $terceroB;

    private string $vehiculoA;

    private string $vehiculoB;

    private User $usuarioSucursal;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresa = $this->crearEmpresa();
        $this->sucursalA = $this->crearSucursal('Sede A');
        $this->sucursalB = $this->crearSucursal('Sede B');

        $this->terceroA = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_number' => '70000001',
            'first_name' => 'TERCERO',
            'last_name' => 'SEDE A',
            'email' => 'tercero.a@test.local',
            'branch_uuid' => $this->sucursalA,
            'is_active' => 1,
        ]);
        $this->terceroB = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_number' => '70000002',
            'first_name' => 'TERCERO',
            'last_name' => 'SEDE B',
            'email' => 'tercero.b@test.local',
            'branch_uuid' => $this->sucursalB,
            'is_active' => 1,
        ]);

        $this->vehiculoA = $this->crearVehiculo('AAA111', $this->terceroA, $this->sucursalA);
        $this->vehiculoB = $this->crearVehiculo('BBB222', $this->terceroB, $this->sucursalB);

        $this->usuarioSucursal = $this->crearUsuarioSucursal($this->terceroA);
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

    private function crearSucursal(string $nombre): string
    {
        $ciudad = $this->insertar('cities', ['name' => 'Ciudad '.$nombre]);

        return $this->insertar('branches', [
            'company_uuid' => $this->empresa,
            'name' => $nombre,
            'address' => 'Calle '.$nombre,
            'municipality_uuid' => $ciudad,
        ]);
    }

    private function crearVehiculo(string $placa, string $tercero, string $sucursal): string
    {
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'third_party_uuid' => $tercero,
            'vehicle_license_plate' => $placa,
            'is_active' => 1,
        ]);

        $this->insertar('vehicles_branches', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculo,
            'branch_uuid' => $sucursal,
            'entry_date' => '2026-01-01',
            'exit_date' => '2026-01-01',
            'exit_type' => 'ENTRADA',
            'is_active' => 1,
        ]);

        return $vehiculo;
    }

    private function crearUsuarioSucursal(string $tercero): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario Sucursal',
            'email' => 'sucursal.a@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole(Role::findOrCreate('SUCURSAL', 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'third_party_uuid' => $tercero,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function comoSucursal()
    {
        Sanctum::actingAs($this->usuarioSucursal, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresa]);
    }

    private function comoAdmin(): User
    {
        $admin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Empresa',
            'email' => 'admin.sucursal@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('ADMIN_EMPRESA', 'api'));
        DB::table('company_user')->insert([
            'user_id' => $admin->id,
            'company_uuid' => $this->empresa,
            'third_party_uuid' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin->fresh();
    }

    public function test_el_usuario_sucursal_solo_ve_los_terceros_de_su_sede(): void
    {
        $r = $this->comoSucursal()->getJson('/api/v1/third-parties?per_page=50');

        $r->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame($this->terceroA, $r->json('data.data.0.uuid'));
    }

    public function test_el_usuario_sucursal_solo_ve_los_vehiculos_de_su_sede(): void
    {
        $r = $this->comoSucursal()->getJson('/api/v1/fleet-management/vehicles?per_page=50');

        $r->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame('AAA111', $r->json('data.data.0.vehicle_license_plate'));
    }

    public function test_el_usuario_sucursal_no_abre_por_uuid_un_vehiculo_de_otra_sede(): void
    {
        $this->comoSucursal()
            ->getJson('/api/v1/fleet-management/vehicles/'.$this->vehiculoB)
            ->assertNotFound();
    }

    public function test_el_usuario_sucursal_solo_ve_los_documentos_de_su_sede(): void
    {
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculoA,
        ]);
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculoB,
        ]);

        $r = $this->comoSucursal()->getJson('/api/v1/fleet-management/vehicle-documents?per_page=50');

        $r->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_el_usuario_sucursal_solo_ve_su_sede_en_el_catalogo(): void
    {
        $r = $this->comoSucursal()->getJson(
            '/api/v1/administration/branches/list?company_uuid='.$this->empresa
        );

        $r->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertSame($this->sucursalA, $r->json('data.0.uuid'));
    }

    public function test_un_usuario_sucursal_no_puede_crear_un_tercero_en_otra_sede(): void
    {
        $tipoDocumento = $this->insertar('type_of_documents', ['name' => 'CC']);
        $ciudad = $this->insertar('cities', ['name' => 'Cali']);
        $responsabilidad = $this->insertar('tax_responsibilities', ['code' => 'R-99-PN']);

        $datos = [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_type_uuid' => $tipoDocumento,
            'document_number' => '70000099',
            'first_name' => 'NUEVO',
            'last_name' => 'TERCERO',
            'email' => 'nuevo.sucursal@test.local',
            'municipality_uuid' => $ciudad,
            'branch_uuid' => $this->sucursalB,
            'id_card_issue_place_uuid' => $ciudad,
            'id_card_issue_date' => '2010-05-20',
            'sex' => 'M',
            'marital_status' => 'SOLTERO',
            'blood_type' => 'O+',
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $responsabilidad,
            'is_active' => true,
        ];

        $this->comoSucursal()->postJson('/api/v1/third-parties', $datos)->assertCreated();

        $this->assertSame(
            $this->sucursalA,
            ThirdParty::withoutGlobalScopes()->where('document_number', '70000099')->value('branch_uuid')
        );
    }

    public function test_un_usuario_sucursal_puede_crear_sin_enviar_la_sede_y_queda_en_la_suya(): void
    {
        $tipoDocumento = $this->insertar('type_of_documents', ['name' => 'CC']);
        $ciudad = $this->insertar('cities', ['name' => 'Cali']);
        $responsabilidad = $this->insertar('tax_responsibilities', ['code' => 'R-98-PN']);

        $datos = [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_type_uuid' => $tipoDocumento,
            'document_number' => '70000098',
            'first_name' => 'SIN',
            'last_name' => 'SEDE ENVIADA',
            'email' => 'sin.sede.enviada@test.local',
            'municipality_uuid' => $ciudad,
            'id_card_issue_place_uuid' => $ciudad,
            'id_card_issue_date' => '2010-05-20',
            'sex' => 'M',
            'marital_status' => 'SOLTERO',
            'blood_type' => 'O+',
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $responsabilidad,
            'is_active' => true,
        ];

        $this->comoSucursal()->postJson('/api/v1/third-parties', $datos)->assertCreated();

        $this->assertSame(
            $this->sucursalA,
            ThirdParty::withoutGlobalScopes()->where('document_number', '70000098')->value('branch_uuid')
        );
    }

    public function test_el_administrador_no_puede_asignar_una_sede_de_otra_empresa(): void
    {
        $otraEmpresa = $this->crearEmpresa();
        $ciudadAjena = $this->insertar('cities', ['name' => 'Ciudad Ajena']);
        $sedeAjena = $this->insertar('branches', [
            'company_uuid' => $otraEmpresa,
            'name' => 'Sede Ajena',
            'address' => 'Calle Ajena',
            'municipality_uuid' => $ciudadAjena,
            'status' => 1,
        ]);

        $tipoDocumento = $this->insertar('type_of_documents', ['name' => 'CC']);
        $ciudad = $this->insertar('cities', ['name' => 'Cali']);
        $responsabilidad = $this->insertar('tax_responsibilities', ['code' => 'R-97-PN']);

        $datos = [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_type_uuid' => $tipoDocumento,
            'document_number' => '70000097',
            'first_name' => 'SEDE',
            'last_name' => 'AJENA',
            'email' => 'sede.ajena@test.local',
            'municipality_uuid' => $ciudad,
            'branch_uuid' => $sedeAjena,
            'id_card_issue_place_uuid' => $ciudad,
            'id_card_issue_date' => '2010-05-20',
            'sex' => 'M',
            'marital_status' => 'SOLTERO',
            'blood_type' => 'O+',
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $responsabilidad,
            'is_active' => true,
        ];

        Sanctum::actingAs($this->comoAdmin(), ['*']);

        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->postJson('/api/v1/third-parties', $datos)
            ->assertStatus(422);

        $campos = collect($respuesta->json('error.details'))->pluck('field');
        $this->assertContains('branch_uuid', $campos);

        $this->assertNull(
            ThirdParty::withoutGlobalScopes()->where('document_number', '70000097')->first()
        );
    }

    public function test_el_administrador_sigue_viendo_todas_las_sedes(): void
    {
        Sanctum::actingAs($this->comoAdmin(), ['*']);

        $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson('/api/v1/third-parties?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson('/api/v1/fleet-management/vehicles?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }
}
