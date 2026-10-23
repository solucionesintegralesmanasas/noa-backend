<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fleet\AffiliateAdminChargeService;
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
 * Ordenamiento server-side del listado de cobros de administración (contrato `sort_by`/`sort_order`).
 */
class OrdenamientoCargosAdministracionTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/fleet-management/affiliate-admin-charges';

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa orden cobros', 'is_active' => true]);
    }

    private function vehiculo(string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ]);
    }

    private function cobro(string $vehiculoUuid, float $monto, array $extra = []): string
    {
        return $this->insertar('affiliate_admin_charges', $extra + [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculoUuid,
            'payment_reference' => 'REF-'.Str::random(6),
            'charge_type' => 'CUOTA_ADMINISTRACION',
            'concept' => 'Cuota mensual',
            'amount' => $monto,
            'period_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'status' => 'PENDIENTE',
        ]);
    }

    private function listar(?string $sortBy, ?string $sortOrder = 'asc', int $perPage = 10, int $page = 1)
    {
        $servicio = app(AffiliateAdminChargeService::class);
        $paginador = $servicio->getAllAffiliateAdminChargesWithPagination(
            perPage: $perPage,
            page: $page,
            search: '',
            companyUuid: $this->empresa,
            thirdPartyUuid: null,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        return $paginador->items();
    }

    public function test_ordena_por_amount_asc_y_desc(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->cobro($v, 100000);
        $this->cobro($v, 300000);
        $this->cobro($v, 200000);

        $asc = collect($this->listar('amount', 'asc'))->pluck('amount')->all();
        $desc = collect($this->listar('amount', 'desc'))->pluck('amount')->all();

        $this->assertSame([100000.0, 200000.0, 300000.0], array_map('floatval', $asc));
        $this->assertSame([300000.0, 200000.0, 100000.0], array_map('floatval', $desc));
    }

    public function test_ordena_por_relacion_vehiculo_placa(): void
    {
        $v1 = $this->vehiculo('ZZZ999');
        $v2 = $this->vehiculo('AAA111');
        $v3 = $this->vehiculo('MMM555');

        $this->cobro($v1, 100, ['payment_reference' => 'REF-Z']);
        $this->cobro($v2, 100, ['payment_reference' => 'REF-A']);
        $this->cobro($v3, 100, ['payment_reference' => 'REF-M']);

        $asc = collect($this->listar('vehicle.vehicle_license_plate', 'asc'))->pluck('payment_reference')->all();

        $this->assertSame(['REF-A', 'REF-M', 'REF-Z'], $asc);
    }

    public function test_ignora_campo_fuera_de_la_lista_blanca(): void
    {
        $v = $this->vehiculo('AAA001');
        $this->cobro($v, 100);
        $this->cobro($v, 200);

        $items = $this->listar('campo_desconocido', 'asc');
        $this->assertCount(2, $items);
    }

    public function test_endpoint_acepta_sort_by_y_sort_order_y_retorna_placa(): void
    {
        $v = $this->vehiculo('PLACA777');
        $this->cobro($v, 100, ['payment_reference' => 'REF-001']);
        $this->cobro($v, 900, ['payment_reference' => 'REF-999']);

        $usuario = $this->usuarioAdministrador();

        Sanctum::actingAs($usuario, ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&sort_by=amount&sort_order=desc');

        $respuesta->assertOk();
        $refs = collect($respuesta->json('data.data'))->pluck('payment_reference')->values()->all();
        $this->assertSame(['REF-999', 'REF-001'], $refs);

        // Verifica además que la placa y el vehicle_uuid se retornen para la UI
        $primerFila = $respuesta->json('data.data.0');
        $this->assertSame('PLACA777', $primerFila['vehicle']['vehicle_license_plate']);
        $this->assertSame($v, $primerFila['vehicle_uuid']);
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('affiliate_admin_charges.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin cobros',
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
