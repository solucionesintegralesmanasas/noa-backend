<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fleet\OperationCardService;
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
 * Ordenamiento server-side del listado de tarjetas de operación (contrato `sort_by`/`sort_order`).
 */
class OrdenamientoTarjetasOperacionTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/fleet-management/operation-cards';

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa orden tarjetas', 'is_active' => true]);
    }

    private function vehiculo(string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ]);
    }

    private function tarjeta(string $vehiculoUuid, string $numero, array $extra = []): string
    {
        return $this->insertar('operation_cards', $extra + [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculoUuid,
            'operating_card_number' => $numero,
            'service_type' => 'ESPECIAL',
            'transport_mode' => 'PASAJEROS',
            'expiration_date' => '2028-06-30',
            'status' => true,
        ]);
    }

    private function listar(?string $sortBy, ?string $sortOrder = 'asc', int $perPage = 10, int $page = 1)
    {
        $servicio = app(OperationCardService::class);
        $paginador = $servicio->getAllOperationCardsWithPagination(
            perPage: $perPage,
            page: $page,
            search: '',
            companyUuid: $this->empresa,
            thirdPartyUuid: null,
            vehicleUuid: null,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        return $paginador->items();
    }

    public function test_ordena_por_operating_card_number_asc_y_desc(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->tarjeta($v, 'TAR-100');
        $this->tarjeta($v, 'TAR-300');
        $this->tarjeta($v, 'TAR-200');

        $asc = collect($this->listar('operating_card_number', 'asc'))->pluck('operating_card_number')->all();
        $desc = collect($this->listar('operating_card_number', 'desc'))->pluck('operating_card_number')->all();

        $this->assertSame(['TAR-100', 'TAR-200', 'TAR-300'], $asc);
        $this->assertSame(['TAR-300', 'TAR-200', 'TAR-100'], $desc);
    }

    public function test_ordena_por_relacion_vehiculo_placa(): void
    {
        $v1 = $this->vehiculo('ZZZ999');
        $v2 = $this->vehiculo('AAA111');
        $v3 = $this->vehiculo('MMM555');

        $this->tarjeta($v1, 'TAR-Z');
        $this->tarjeta($v2, 'TAR-A');
        $this->tarjeta($v3, 'TAR-M');

        $asc = collect($this->listar('vehicle.vehicle_license_plate', 'asc'))->pluck('operating_card_number')->all();

        $this->assertSame(['TAR-A', 'TAR-M', 'TAR-Z'], $asc);
    }

    public function test_ignora_campo_fuera_de_la_lista_blanca(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->tarjeta($v, 'TAR-1');
        $this->tarjeta($v, 'TAR-2');

        $items = $this->listar('campo_desconocido', 'asc');
        $this->assertCount(2, $items);
    }

    public function test_endpoint_acepta_sort_by_y_sort_order(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->tarjeta($v, 'TAR-001');
        $this->tarjeta($v, 'TAR-999');

        $usuario = $this->usuarioAdministrador();

        Sanctum::actingAs($usuario, ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&sort_by=operating_card_number&sort_order=desc');

        $respuesta->assertOk();
        $nums = collect($respuesta->json('data.data'))->pluck('operating_card_number')->values()->all();
        $this->assertSame(['TAR-999', 'TAR-001'], $nums);
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('operation_cards.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin tarjetas',
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
