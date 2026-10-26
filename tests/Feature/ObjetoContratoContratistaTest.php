<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ContractExtraction\ContractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Autocompletado del FUEC: al digitar el número de documento de un contratante
 * ya registrado, además de sus datos el formulario precarga el OBJETO DEL
 * CONTRATO de su último FUEC (fuecs.object_contract_uuid, consultado vía
 * GET contract-extract/contractors?search=...).
 */
class ObjetoContratoContratistaTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private const BASE = '/api/v1/contract-extract/contractors';

    private string $empresa;

    private string $otraEmpresa;

    private string $objetoA;

    private string $objetoB;

    private string $contratante;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);

        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa objeto contrato', 'is_active' => true]);
        $this->otraEmpresa = $this->insertar('companies', ['business_name' => 'Otra empresa objeto', 'is_active' => true]);
        $this->objetoA = $this->insertar('objects_contracts', ['name' => 'Servicio Escolar']);
        $this->objetoB = $this->insertar('objects_contracts', ['name' => 'Turismo']);
        $this->contratante = $this->insertar('contractors', [
            'company_uuid' => $this->empresa,
            'company_name' => 'Contratista Objeto',
            'document_number' => '900000777',
        ]);
    }

    // ── Utilidades ──────────────────────────────────────────────────────────────

    /**
     * Crea un FUEC completo con un vehículo, un conductor y un contratante propios.
     *
     * @param  array<string, string>  $datos
     */
    private function fuec(string $objeto, array $datos = []): void
    {
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $datos['company_uuid'] ?? $this->empresa,
            'vehicle_license_plate' => 'OC'.Str::random(3),
            'internal_number' => 'OC-'.Str::random(4),
        ]);

        $this->insertar('fuec', array_merge([
            'company_uuid' => $this->empresa,
            'contractor_uuid' => $this->contratante,
            'vehicle_uuid' => $vehiculo,
            'object_contract_uuid' => $objeto,
            'main_conductor_uuid' => $this->insertar('third_parties', ['company_uuid' => $this->empresa]),
            'issue_date' => '2026-10-01',
            'request_number' => 'REQ'.Str::random(12),
            'number_fuec' => '0'.Str::random(3),
            'contract_number_display' => '00'.Str::random(2),
            'verification_code' => 'VC-'.Str::random(10),
            'status' => 'ACTIVO',
        ], $datos));
    }

    /** Contratistas paginados por término de búsqueda. */
    private function buscar(string $busqueda): \Illuminate\Support\Collection
    {
        DB::enableQueryLog();

        return app(ContractorService::class)
            ->getAllContractorsWithPagination(15, 1, $busqueda, $this->empresa)
            ->getCollection();
    }

    /**
     * Usuario ADMIN_EMPRESA vinculado a la empresa de la prueba, como exige SetCompanyContext.
     */
    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('contract_extractions.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin objeto contrato',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
        ]);

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('ADMIN_EMPRESA', 'api'));
        $user->givePermissionTo(Permission::all());

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

    // ── Casos del servicio ──────────────────────────────────────────────────────

    public function test_el_contratante_trae_el_objeto_del_contrato_de_su_ultimo_fuec(): void
    {
        $this->fuec($this->objetoA);
        $this->fuec($this->objetoB);
        $ajeno = $this->insertar('contractors', [
            'company_uuid' => $this->otraEmpresa,
            'company_name' => 'Contratista Ajeno',
            'document_number' => '900000777',
        ]);
        $this->fuec($this->objetoA, ['company_uuid' => $this->otraEmpresa, 'contractor_uuid' => $ajeno]);

        $contratante = $this->buscar('900000777')->firstWhere('uuid', $this->contratante);

        $this->assertNotNull($contratante);
        $this->assertSame($this->objetoB, $contratante->object_contract_uuid, 'Debe traer el objeto del FUEC más reciente (mayor id)');
    }

    public function test_un_contratista_sin_fuec_trae_objeto_nulo_y_otro_con_fuec_lo_trae(): void
    {
        $otro = $this->insertar('contractors', [
            'company_uuid' => $this->empresa,
            'company_name' => 'Contratista Sin FUEC',
            'document_number' => '800000222',
        ]);
        $this->fuec($this->objetoA);

        $resultado = $this->buscar('');

        $this->assertSame($this->objetoA, $resultado->firstWhere('uuid', $this->contratante)?->object_contract_uuid);
        $this->assertNull($resultado->firstWhere('uuid', $otro)?->object_contract_uuid, 'Sin FUEC el campo viene en null, no vacío estructural');
    }

    public function test_sin_resultados_no_consulta_fuec(): void
    {
        DB::enableQueryLog();

        $vacio = $this->buscar('documento-que-no-existe');

        $this->assertTrue($vacio->isEmpty());
        $consultasFuec = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains(strtolower((string) $q['query']), 'max(id)'))
            ->count();

        $this->assertSame(0, $consultasFuec, 'Sin contratistas no debe lanzar la consulta de máximo');
    }

    // ── Cableado HTTP ───────────────────────────────────────────────────────────

    public function test_el_endpoint_http_trae_objeto_y_nulo_sin_fuec(): void
    {
        $sinFuec = $this->insertar('contractors', [
            'company_uuid' => $this->empresa,
            'company_name' => 'Contratista Sin FUEC HTTP',
            'document_number' => '800000333',
        ]);
        $this->fuec($this->objetoB);

        Sanctum::actingAs($this->usuarioAdministrador(), ['*']);

        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&search=');

        $respuesta->assertOk();
        $filas = collect($respuesta->json('data.data'));
        $conFuec = $filas->firstWhere('document_number', '900000777');
        $sinObjeto = $filas->firstWhere('document_number', '800000333');

        $this->assertSame($this->objetoB, $conFuec['object_contract_uuid']);
        $this->assertNull($sinObjeto['object_contract_uuid']);
    }
}
