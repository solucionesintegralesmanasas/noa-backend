<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fleet\VehicleInspectionService;
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
 * Ordenamiento server-side del listado de inspecciones de vehículo (contrato `sort_by`/`sort_order`).
 */
class OrdenamientoInspeccionesVehiculoTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/fleet-management/vehicle-inspections';

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa orden inspecciones', 'is_active' => true]);
    }

    private function vehiculo(string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ]);
    }

    private function conductor(string $nombre): string
    {
        return $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'document_number' => (string) mt_rand(10000000, 99999999),
            'first_name' => $nombre,
            'last_name' => 'Apellido',
            'is_driver' => true,
            'is_active' => true,
        ]);
    }

    private function inspeccion(string $vehiculoUuid, array $extra = []): string
    {
        return $this->insertar('vehicle_inspections', $extra + [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculoUuid,
            'inspection_date' => now()->toDateString(),
            'inspector_name' => 'Inspector General',
            'mileage' => 50000,
        ]);
    }

    private function listar(?string $sortBy, ?string $sortOrder = 'asc', int $perPage = 10, int $page = 1)
    {
        $servicio = app(VehicleInspectionService::class);
        $paginador = $servicio->getAllVehicleInspectionsWithPagination(
            perPage: $perPage,
            page: $page,
            search: '',
            companyUuid: $this->empresa,
            thirdPartyUuid: null,
            vehicleUuid: null,
            driverUuid: null,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        return $paginador->items();
    }

    public function test_ordena_por_mileage_asc_y_desc(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->inspeccion($v, ['mileage' => 10000]);
        $this->inspeccion($v, ['mileage' => 30000]);
        $this->inspeccion($v, ['mileage' => 20000]);

        $asc = collect($this->listar('mileage', 'asc'))->pluck('mileage')->all();
        $desc = collect($this->listar('mileage', 'desc'))->pluck('mileage')->all();

        $this->assertSame([10000.0, 20000.0, 30000.0], array_map('floatval', $asc));
        $this->assertSame([30000.0, 20000.0, 10000.0], array_map('floatval', $desc));
    }

    public function test_ordena_por_relacion_vehiculo_placa(): void
    {
        $v1 = $this->vehiculo('ZZZ999');
        $v2 = $this->vehiculo('AAA111');
        $v3 = $this->vehiculo('MMM555');

        $this->inspeccion($v1, ['inspector_name' => 'Insp-Z']);
        $this->inspeccion($v2, ['inspector_name' => 'Insp-A']);
        $this->inspeccion($v3, ['inspector_name' => 'Insp-M']);

        $asc = collect($this->listar('vehicle.vehicle_license_plate', 'asc'))->pluck('inspector_name')->all();

        $this->assertSame(['Insp-A', 'Insp-M', 'Insp-Z'], $asc);
    }

    public function test_ordena_por_relacion_conductor_nombre(): void
    {
        $v = $this->vehiculo('AAA001');
        $c1 = $this->conductor('Carlos');
        $c2 = $this->conductor('Alberto');
        $c3 = $this->conductor('Zacarias');

        $this->inspeccion($v, ['driver_uuid' => $c1, 'inspector_name' => 'Insp-C']);
        $this->inspeccion($v, ['driver_uuid' => $c2, 'inspector_name' => 'Insp-A']);
        $this->inspeccion($v, ['driver_uuid' => $c3, 'inspector_name' => 'Insp-Z']);

        $asc = collect($this->listar('driver.first_name', 'asc'))->pluck('inspector_name')->all();

        $this->assertSame(['Insp-A', 'Insp-C', 'Insp-Z'], $asc);
    }

    public function test_ignora_campo_fuera_de_la_lista_blanca(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->inspeccion($v, ['inspector_name' => 'Insp-1']);
        $this->inspeccion($v, ['inspector_name' => 'Insp-2']);

        $items = $this->listar('campo_desconocido', 'asc');
        $this->assertCount(2, $items);
    }

    public function test_endpoint_acepta_sort_by_y_sort_order(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->inspeccion($v, ['inspector_name' => 'Alpha', 'mileage' => 100]);
        $this->inspeccion($v, ['inspector_name' => 'Omega', 'mileage' => 900]);

        $usuario = $this->usuarioAdministrador();

        Sanctum::actingAs($usuario, ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&sort_by=inspector_name&sort_order=desc');

        $respuesta->assertOk();
        $inspectores = collect($respuesta->json('data.data'))->pluck('inspector_name')->values()->all();
        $this->assertSame(['Omega', 'Alpha'], $inspectores);
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('vehicle_inspections.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin inspecciones',
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
