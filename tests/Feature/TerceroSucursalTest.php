<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ThirdParty;
use App\Models\User;
use App\Services\Administrations\CompanyService;
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
 * La sucursal del tercero es obligatoria en la capa de aplicación (formularios y
 * FormRequests), aunque la columna sea nullable para no romper los terceros que
 * ya existían sin sucursal. La sucursal debe pertenecer a la misma empresa del
 * tercero y, al crear una empresa, su tercero queda en la Sede Principal.
 */
class TerceroSucursalTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $branch;

    private string $documentType;

    private string $city;

    private string $taxResponsibility;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresa = $this->crearEmpresa();
        $this->documentType = $this->insertar('type_of_documents', ['name' => 'CC']);
        $this->city = $this->insertar('cities', ['name' => 'Cali']);
        $this->taxResponsibility = $this->insertar('tax_responsibilities', ['code' => 'R-99-PN']);

        $this->branch = $this->insertar('branches', [
            'company_uuid' => $this->empresa,
            'name' => 'Sucursal Centro',
            'address' => 'Calle 10 # 20-30',
            'municipality_uuid' => $this->city,
        ]);

        $this->admin = $this->usuario('ADMIN_EMPRESA', $this->empresa, 'admin.sucursal@test.local');
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

    private function usuario(string $rol, string $empresa, string $email): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba '.$rol, 'email' => $email,
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        $user->assignRole(Role::findOrCreate($rol, 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $empresa, 'third_party_uuid' => null,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function datosTercero(array $overrides = []): array
    {
        return array_merge([
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_type_uuid' => $this->documentType,
            'document_number' => (string) random_int(10000000, 99999999),
            'first_name' => 'ANA',
            'last_name' => 'PEREZ',
            'email' => Str::random(8).'@tercero.test',
            'municipality_uuid' => $this->city,
            'branch_uuid' => $this->branch,
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $this->taxResponsibility,
            'is_active' => true,
        ], $overrides);
    }

    private function crear(array $datos)
    {
        Sanctum::actingAs($this->admin, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->postJson('/api/v1/third-parties', $datos);
    }

    private function assertErrorDeSucursal($response): void
    {
        $response->assertStatus(422);
        $campos = collect($response->json('error.details'))->pluck('field');
        $this->assertTrue($campos->contains('branch_uuid'), 'Debe señalar branch_uuid: '.$campos->implode(', '));
    }

    public function test_crear_un_tercero_sin_sucursal_es_rechazado(): void
    {
        $datos = $this->datosTercero();
        unset($datos['branch_uuid']);

        $this->assertErrorDeSucursal($this->crear($datos));

        $this->assertFalse(ThirdParty::withoutGlobalScopes()->where('email', $datos['email'])->exists());
    }

    public function test_crear_un_tercero_con_sucursal_de_su_empresa_la_guarda(): void
    {
        $datos = $this->datosTercero();

        $this->crear($datos)->assertCreated();

        $this->assertSame(
            $this->branch,
            ThirdParty::withoutGlobalScopes()->where('document_number', $datos['document_number'])->value('branch_uuid')
        );
    }

    public function test_no_permite_una_sucursal_de_otra_empresa(): void
    {
        $otraEmpresa = $this->crearEmpresa();
        $sucursalAjena = $this->insertar('branches', [
            'company_uuid' => $otraEmpresa,
            'name' => 'Sucursal Ajena',
            'address' => 'Calle 99',
            'municipality_uuid' => $this->city,
        ]);

        $this->assertErrorDeSucursal($this->crear($this->datosTercero(['branch_uuid' => $sucursalAjena])));
    }

    public function test_editar_un_tercero_existente_sin_sucursal_la_exige(): void
    {
        $uuid = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_number' => '55500001',
            'first_name' => 'LEGADO',
            'last_name' => 'ANTIGUO',
            'email' => 'legado@tercero.test',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->putJson('/api/v1/third-parties/'.$uuid, ['branch_uuid' => null]);

        $this->assertErrorDeSucursal($response);
    }

    public function test_editar_un_tercero_le_asigna_la_sucursal(): void
    {
        $uuid = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_number' => '55500002',
            'first_name' => 'LEGADO',
            'last_name' => 'ANTIGUO',
            'email' => 'legado2@tercero.test',
            'is_active' => 1,
        ]);

        Sanctum::actingAs($this->admin, ['*']);
        $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->putJson('/api/v1/third-parties/'.$uuid, ['branch_uuid' => $this->branch])
            ->assertOk();

        $this->assertSame(
            $this->branch,
            ThirdParty::withoutGlobalScopes()->where('uuid', $uuid)->value('branch_uuid')
        );
    }

    public function test_el_listado_devuelve_la_sucursal_del_tercero(): void
    {
        $datos = $this->datosTercero();
        $this->crear($datos)->assertCreated();

        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson('/api/v1/third-parties?search='.$datos['document_number']);

        $response->assertOk();
        $fila = collect($response->json('data.data'))->firstWhere('document_number', $datos['document_number']);
        $this->assertNotNull($fila);
        $this->assertSame($this->branch, $fila['branch_uuid']);
        $this->assertSame('Sucursal Centro', $fila['branch']['name']);
    }

    public function test_al_crear_una_empresa_su_tercero_queda_en_la_sede_principal(): void
    {
        Role::findOrCreate('ADMIN_EMPRESA', 'api');
        $tipoDocumento = $this->insertar('type_of_documents', ['name' => 'NIT']);
        $ciudad = $this->insertar('cities', ['name' => 'Bogotá']);
        $regimen = $this->insertar('tax_regimes', ['code' => '48']);

        $documento = (string) random_int(900000000, 999999999);
        $empresa = app(CompanyService::class)->createCompany([
            'person_type' => 'PERSONA JURIDICA',
            'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $tipoDocumento,
            'document_number' => $documento,
            'business_name' => 'NUEVA EMPRESA S.A.S',
            'municipality_uuid' => $ciudad,
            'address' => 'Calle 1 # 2-3',
            'email' => 'nueva.empresa@test.local',
            'tax_regime_uuid' => $regimen,
            'web_page' => 'https://nueva.test',
            'legal_representative_name' => 'REP',
            'legal_representative_last_name' => 'LEGAL',
            'legal_representative_document_type' => 'CC',
            'legal_representative_document_number' => '123456',
        ]);

        $tercero = ThirdParty::withoutGlobalScopes()->where('company_uuid', $empresa->uuid)->firstOrFail();
        $sucursal = DB::table('branches')->where('uuid', $tercero->branch_uuid)->first();

        $this->assertNotNull($sucursal);
        $this->assertSame('Sede Principal', $sucursal->name);
        $this->assertSame($empresa->uuid, $sucursal->company_uuid);
    }
}
