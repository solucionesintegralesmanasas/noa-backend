<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fleet\VehicleDocumentService;
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
 * Ordenamiento server-side del listado de documentos de vehículo (contrato `sort_by`/`sort_order`).
 */
class OrdenamientoDocumentosVehiculoTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/fleet-management/vehicle-documents';

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa orden docs', 'is_active' => true]);
    }

    private function vehiculo(string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ]);
    }

    private function documento(string $vehiculoUuid, string $numero, array $extra = []): string
    {
        return $this->insertar('vehicle_documents', $extra + [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculoUuid,
            'document_type' => 'SOAT',
            'policy_number' => $numero,
            'issuing_entity' => 'Seguros Bolivar',
            'expiry_date' => '2027-12-31',
            'status' => 'VIGENTE',
        ]);
    }

    private function listar(?string $sortBy, ?string $sortOrder = 'asc', int $perPage = 10, int $page = 1)
    {
        $servicio = app(VehicleDocumentService::class);
        $paginador = $servicio->getAllVehicleDocumentsWithPagination(
            perPage: $perPage,
            page: $page,
            search: '',
            companyUuid: $this->empresa,
            documentType: null,
            thirdPartyUuid: null,
            vehicleUuid: null,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        return $paginador->items();
    }

    public function test_ordena_por_policy_number_asc_y_desc(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->documento($v, 'POL-100');
        $this->documento($v, 'POL-300');
        $this->documento($v, 'POL-200');

        $asc = collect($this->listar('policy_number', 'asc'))->pluck('policy_number')->all();
        $desc = collect($this->listar('policy_number', 'desc'))->pluck('policy_number')->all();

        $this->assertSame(['POL-100', 'POL-200', 'POL-300'], $asc);
        $this->assertSame(['POL-300', 'POL-200', 'POL-100'], $desc);
    }

    public function test_ordena_por_relacion_vehiculo_placa(): void
    {
        $v1 = $this->vehiculo('ZZZ999');
        $v2 = $this->vehiculo('AAA111');
        $v3 = $this->vehiculo('MMM555');

        $this->documento($v1, 'DOC-Z');
        $this->documento($v2, 'DOC-A');
        $this->documento($v3, 'DOC-M');

        $asc = collect($this->listar('vehicle.vehicle_license_plate', 'asc'))->pluck('policy_number')->all();

        $this->assertSame(['DOC-A', 'DOC-M', 'DOC-Z'], $asc);
    }

    public function test_ignora_campo_fuera_de_la_lista_blanca(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->documento($v, 'POL-1');
        $this->documento($v, 'POL-2');

        $items = $this->listar('campo_desconocido', 'asc');
        $this->assertCount(2, $items);
    }

    public function test_endpoint_acepta_sort_by_y_sort_order(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->documento($v, 'POL-001');
        $this->documento($v, 'POL-999');

        $usuario = $this->usuarioAdministrador();

        Sanctum::actingAs($usuario, ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&sort_by=policy_number&sort_order=desc');

        $respuesta->assertOk();
        $nums = collect($respuesta->json('data.data'))->pluck('policy_number')->values()->all();
        $this->assertSame(['POL-999', 'POL-001'], $nums);
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('vehicle_documents.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin docs',
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
