<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fleet\VehicleService;
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
 * Ordenamiento server-side del listado de vehículos (contrato `sort_by`/`sort_order`).
 *
 * El DataTable del frontend es `lazy`: sin orden en el servidor la cabecera pinta la
 * flecha pero la fila no se mueve. Este test fija el contrato y la lista blanca.
 */
class OrdenamientoVehiculosTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/fleet-management/vehicles';

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa orden', 'is_active' => true]);
    }

    // ── Utilidades ──────────────────────────────────────────────────────────────

    private function vehiculo(string $placa, array $extra = []): string
    {
        return $this->insertar('vehicles', $extra + [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ]);
    }

    private function marca(string $descripcion): string
    {
        return $this->insertar('brands', ['description' => $descripcion]);
    }

    private function clase(string $descripcion): string
    {
        return $this->insertar('vehicle_class', ['description' => $descripcion]);
    }

    private function sede(string $nombre): string
    {
        return $this->insertar('branches', [
            'company_uuid' => $this->empresa,
            'name' => $nombre,
        ]);
    }

    /** @return array<int, string> */
    private function placas($paginator): array
    {
        return collect($paginator->items())->pluck('vehicle_license_plate')->values()->all();
    }

    /** @return array<int, string> */
    private function descripcionesDeMarca($paginator): array
    {
        return collect($paginator->items())->pluck('brand.description')->values()->all();
    }

    private function listar(?string $sortBy, ?string $sortOrder = 'asc', int $perPage = 15, int $page = 1)
    {
        return app(VehicleService::class)
            ->getAllVehiclesWithPagination($perPage, $page, '', $this->empresa, null, $sortBy, $sortOrder);
    }

    // ── Columnas propias de la tabla ────────────────────────────────────────────

    public function test_ordena_por_placa_en_ambas_direcciones(): void
    {
        $this->vehiculo('ZZZ999');
        $this->vehiculo('AAA111');
        $this->vehiculo('MMM555');

        $this->assertSame(['AAA111', 'MMM555', 'ZZZ999'], $this->placas($this->listar('vehicle_license_plate', 'asc')));
        $this->assertSame(['ZZZ999', 'MMM555', 'AAA111'], $this->placas($this->listar('vehicle_license_plate', 'desc')));
    }

    public function test_la_direccion_invalida_cae_a_ascendente(): void
    {
        $this->vehiculo('ZZZ999');
        $this->vehiculo('AAA111');

        $this->assertSame(['AAA111', 'ZZZ999'], $this->placas($this->listar('vehicle_license_plate', 'borrar; tablas')));
    }

    public function test_un_campo_fuera_de_la_lista_blanca_se_ignora(): void
    {
        $this->vehiculo('ZZZ999');
        $this->vehiculo('AAA111');

        foreach (['vehicles.company_uuid', 'uuid desc; drop table vehicles', 'no_existe'] as $campo) {
            $paginator = $this->listar($campo, 'desc');

            $this->assertSame(2, $paginator->total(), "El campo {$campo} no debe romper ni filtrar el listado");
            $this->assertEqualsCanonicalizing(['AAA111', 'ZZZ999'], $this->placas($paginator));
        }
    }

    // ── Columnas de relación: se ordena por el texto que ve el usuario ──────────

    public function test_ordena_por_la_descripcion_de_la_marca(): void
    {
        $zeta = $this->marca('Zeta');
        $alfa = $this->marca('Alfa');
        $mita = $this->marca('Mita');

        $this->vehiculo('AAA111', ['brand_uuid' => $zeta]);
        $this->vehiculo('BBB222', ['brand_uuid' => $alfa]);
        $this->vehiculo('CCC333', ['brand_uuid' => $mita]);

        $this->assertSame(['Alfa', 'Mita', 'Zeta'], $this->descripcionesDeMarca($this->listar('brand.description', 'asc')));
        $this->assertSame(['Zeta', 'Mita', 'Alfa'], $this->descripcionesDeMarca($this->listar('brand.description', 'desc')));
    }

    public function test_ordena_por_la_descripcion_de_la_clase(): void
    {
        $una = $this->clase('Camioneta');
        $otra = $this->clase('Automovil');

        $this->vehiculo('AAA111', ['vehicle_class_uuid' => $una]);
        $this->vehiculo('BBB222', ['vehicle_class_uuid' => $otra]);

        $orden = collect($this->listar('vehicle_class.description')->items())
            ->pluck('vehicle_class.description')->values()->all();

        $this->assertSame(['Automovil', 'Camioneta'], $orden);
    }

    public function test_ordena_por_la_sede_activa_del_vehiculo(): void
    {
        $sedeA = $this->sede('Sede Alfa');
        $sedeZ = $this->sede('Sede Zeta');

        $vehiculoA = $this->vehiculo('AAA111');
        $vehiculoZ = $this->vehiculo('ZZZ999');

        $this->insertar('vehicles_branches', [
            'vehicle_uuid' => $vehiculoZ, 'branch_uuid' => $sedeZ, 'is_active' => true,
        ]);
        $this->insertar('vehicles_branches', [
            'vehicle_uuid' => $vehiculoA, 'branch_uuid' => $sedeA, 'is_active' => true,
        ]);

        $this->assertSame(['AAA111', 'ZZZ999'], $this->placas($this->listar('branch.name', 'asc')));
        $this->assertSame(['ZZZ999', 'AAA111'], $this->placas($this->listar('branch.name', 'desc')));
    }

    // ── Paginación determinista ────────────────────────────────────────────────

    public function test_el_desempate_no_repite_ni_omite_filas_entre_paginas(): void
    {
        $mismaMarca = $this->marca('Misma');
        foreach (['AAA111', 'BBB222', 'CCC333', 'DDD444', 'EEE555'] as $placa) {
            $this->vehiculo($placa, ['brand_uuid' => $mismaMarca]);
        }

        $recolectadas = [];
        for ($page = 1; $page <= 3; $page++) {
            foreach ($this->placas($this->listar('brand.description', 'asc', 2, $page)) as $placa) {
                $recolectadas[] = $placa;
            }
        }

        $this->assertSame(5, count($recolectadas), 'Las tres páginas deben cubrir las cinco filas sin repetir');
        $this->assertEqualsCanonicalizing(['AAA111', 'BBB222', 'CCC333', 'DDD444', 'EEE555'], $recolectadas);
    }

    // ── Cableado HTTP ──────────────────────────────────────────────────────────

    public function test_el_endpoint_acepta_sort_by_y_sort_order(): void
    {
        $this->vehiculo('ZZZ999');
        $this->vehiculo('AAA111');

        $usuario = $this->usuarioAdministrador();

        Sanctum::actingAs($usuario, ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&sort_by=vehicle_license_plate&sort_order=desc');

        $respuesta->assertOk();
        $placas = collect($respuesta->json('data.data'))->pluck('vehicle_license_plate')->values()->all();
        $this->assertSame(['ZZZ999', 'AAA111'], $placas);
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('vehicles.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin orden',
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
