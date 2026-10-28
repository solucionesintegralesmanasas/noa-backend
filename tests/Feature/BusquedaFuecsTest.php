<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ContractExtraction\FuecService;
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
 * Búsqueda libre del listado de FUEC.
 *
 * La barra de búsqueda debe encontrar cualquier dato que el usuario ve en la tabla
 * (número, placa, contratante, fecha, ruta y estado) y también el número completo de
 * 23 dígitos, que no se muestra pero se usa a diario.
 */
class BusquedaFuecsTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/contract-extract/fuecs';

    private string $empresa;

    private string $objeto;

    private string $conductor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);

        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa búsqueda FUEC', 'is_active' => true]);
        $this->objeto = $this->insertar('objects_contracts', []);
        $this->conductor = $this->insertar('third_parties', ['company_uuid' => $this->empresa]);
    }

    // ── Utilidades ──────────────────────────────────────────────────────────────

    /**
     * Crea un FUEC completo con un vehículo y un contratante propios.
     *
     * @param  array<string, string>  $datos
     */
    private function fuec(array $datos = []): void
    {
        $contratante = $this->insertar('contractors', [
            'company_uuid' => $this->empresa,
            'company_name' => $datos['contractor_name'] ?? 'Contratista General',
            'document_number' => $datos['contractor_document'] ?? '900000001',
        ]);

        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $datos['plate'] ?? 'ABC123',
            'internal_number' => $datos['internal'] ?? 'INT-001',
        ]);

        $this->insertar('fuec', [
            'company_uuid' => $this->empresa,
            'contractor_uuid' => $contratante,
            'vehicle_uuid' => $vehiculo,
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'issue_date' => $datos['issue_date'] ?? '2026-10-08',
            'request_number' => $datos['request_number'] ?? 'REQ'.Str::random(12),
            'number_fuec' => $datos['number_fuec'] ?? '0001',
            'contract_number_display' => $datos['contract'] ?? '0001',
            'effective_date' => $datos['effective_date'] ?? '2026-10-08',
            'expiration_date' => $datos['expiration_date'] ?? '2026-10-09',
            'origin_route' => $datos['origin'] ?? 'Bogotá',
            'destination_route' => $datos['destination'] ?? 'Medellín',
            'verification_code' => $datos['verification_code'] ?? 'CODE-'.Str::random(10),
            'status' => $datos['status'] ?? 'ACTIVO',
        ]);
    }

    /** @return array<int, string> */
    private function buscar(string $search): array
    {
        return app(FuecService::class)
            ->getAllFuecsWithPagination(15, 1, $search, $this->empresa)
            ->getCollection()
            ->pluck('number_fuec')
            ->all();
    }

    // ── Columnas visibles ──────────────────────────────────────────────────────

    public function test_encuentra_por_numero_fuec_corto_y_por_el_numero_completo(): void
    {
        $this->fuec([
            'number_fuec' => '4821',
            'request_number' => '110011234202600004821',
        ]);
        $this->fuec(['number_fuec' => '9999']);

        $this->assertSame(['4821'], $this->buscar('4821'));
        $this->assertSame(['4821'], $this->buscar('110011234202600004821'), 'El número completo de 23 dígitos no se muestra pero debe encontrarlo');
        $this->assertSame(['4821'], $this->buscar('202600004821'), 'También debe funcionar un fragmento del número completo');
    }

    public function test_encuentra_por_placa_e_interno_del_vehiculo(): void
    {
        $this->fuec(['plate' => 'XYZ987', 'number_fuec' => '1111']);
        $this->fuec(['plate' => 'ABC123', 'internal' => 'INT-777', 'number_fuec' => '2222']);

        $this->assertSame(['1111'], $this->buscar('XYZ987'));
        $this->assertSame(['1111'], $this->buscar('yz9'), 'La búsqueda por placa es parcial');
        $this->assertSame(['2222'], $this->buscar('INT-777'));
    }

    public function test_encuentra_por_nombre_y_documento_del_contratante(): void
    {
        $this->fuec([
            'contractor_name' => 'Transportes del Norte S.A.S.',
            'contractor_document' => '900123456',
            'number_fuec' => '1111',
        ]);
        $this->fuec([
            'contractor_name' => 'Turismo Andino',
            'contractor_document' => '800987654',
            'number_fuec' => '2222',
        ]);

        $this->assertSame(['1111'], $this->buscar('Transportes'));
        $this->assertSame(['1111'], $this->buscar('900123456'));
        $this->assertSame(['2222'], $this->buscar('Andino'));
    }

    public function test_encuentra_por_ruta_de_origen_y_destino(): void
    {
        $this->fuec(['origin' => 'Cali', 'destination' => 'Palmira', 'number_fuec' => '1111']);
        $this->fuec(['origin' => 'Bogotá', 'destination' => 'Tunja', 'number_fuec' => '2222']);

        $this->assertSame(['1111'], $this->buscar('Cali'));
        $this->assertSame(['1111'], $this->buscar('Palmira'));
        $this->assertSame(['2222'], $this->buscar('Tunja'));
    }

    public function test_encuentra_por_estado(): void
    {
        $this->fuec(['status' => 'ACTIVO', 'number_fuec' => '1111']);
        $this->fuec(['status' => 'ANULADO', 'number_fuec' => '2222']);

        $this->assertSame(['2222'], $this->buscar('ANULADO'));
        $this->assertSame(['2222'], $this->buscar('ADO'), 'Un fragmento del estado también debe encontrar');
        $this->assertSame(['1111'], $this->buscar('TIVO'), 'Un fragmento de ACTIVO debe encontrar el activo');
    }

    public function test_encuentra_por_fecha_de_expedicion_en_varios_formatos(): void
    {
        $this->fuec(['issue_date' => '2026-10-08', 'number_fuec' => '1111']);
        $this->fuec(['issue_date' => '2026-11-20', 'number_fuec' => '2222']);

        $this->assertSame(['1111'], $this->buscar('2026-10-08'));
        $this->assertSame(['1111'], $this->buscar('08/10/2026'));
        $this->assertSame(['1111'], $this->buscar('08-10-2026'));
        $this->assertSame(['2222'], $this->buscar('2026-11'));
    }

    // ── Combinación de términos ────────────────────────────────────────────────

    public function test_combina_los_terminos_con_y_para_acotar_el_resultado(): void
    {
        $this->fuec(['plate' => 'ABC123', 'contractor_name' => 'Transportes del Norte', 'number_fuec' => '1111']);
        $this->fuec(['plate' => 'ABC123', 'contractor_name' => 'Turismo Andino', 'number_fuec' => '2222']);

        $this->assertSame(['1111'], $this->buscar('ABC123 Transportes'));
        $this->assertSame([], $this->buscar('ABC123 Inexistente'));
        $this->assertEqualsCanonicalizing(['1111', '2222'], $this->buscar('ABC123 2026-10-08'), 'Ambos coinciden con placa y fecha');
    }

    public function test_ignora_los_comodines_que_escribe_el_usuario(): void
    {
        $this->fuec(['number_fuec' => '1111']);

        $this->assertSame([], $this->buscar('%'), 'Un % suelto no debe devolver todo el listado');
        $this->assertSame([], $this->buscar('_'));
    }

    // ── Multi-tenant ───────────────────────────────────────────────────────────

    public function test_no_mezcla_fuecs_de_otra_empresa(): void
    {
        $this->fuec(['plate' => 'AAA111', 'number_fuec' => '1111']);

        $otraEmpresa = $this->insertar('companies', ['business_name' => 'Otra empresa', 'is_active' => true]);
        $contratante = $this->insertar('contractors', [
            'company_uuid' => $otraEmpresa,
            'company_name' => 'Contratista Ajeno',
            'document_number' => '700000001',
        ]);
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $otraEmpresa,
            'vehicle_license_plate' => 'AAA111',
            'internal_number' => 'INT-AJENO',
        ]);
        $this->insertar('fuec', [
            'company_uuid' => $otraEmpresa,
            'contractor_uuid' => $contratante,
            'vehicle_uuid' => $vehiculo,
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'issue_date' => '2026-10-08',
            'request_number' => 'REQ-AJENO-0001',
            'number_fuec' => '9999',
            'contract_number_display' => '0001',
            'effective_date' => '2026-10-08',
            'expiration_date' => '2026-10-09',
            'origin_route' => 'Bogotá',
            'destination_route' => 'Medellín',
            'verification_code' => 'CODE-AJENO',
            'status' => 'ACTIVO',
        ]);

        $this->assertSame(['1111'], $this->buscar('AAA111'));
    }

    // ── Cableado HTTP ──────────────────────────────────────────────────────────

    public function test_el_endpoint_recibe_el_parametro_search(): void
    {
        $this->fuec(['plate' => 'XYZ987', 'number_fuec' => '4821']);

        Sanctum::actingAs($this->usuarioAdministrador(), ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&search=XYZ987');

        $respuesta->assertOk();
        $this->assertSame(['4821'], collect($respuesta->json('data.data'))->pluck('number_fuec')->all());
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('fuec.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin búsqueda FUEC',
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
}
