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
 * Consecutivo compartido de los contratos de radicación y datos de empresa
 * expuestos al wizard de tarjeta de operación.
 */
class ContratoConsecutivoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $expediente;

    private string $paso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', [
            'business_name' => 'Transportes Consecutivo S.A.S',
            'address' => 'Calle 10 # 20-30',
            'phone' => '3001234567',
            'email' => 'contratos@example.test',
            'legal_representative_name' => 'Ana',
            'legal_representative_last_name' => 'Pérez',
            'is_active' => true,
        ]);
        $this->expediente = $this->insertar('procedures', [
            'company_uuid' => $this->empresa,
            'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'TARJETA_DE_OPERACION',
            'procedure_code' => 'EXP-CONS-1',
            'status' => 'EN_PROCESO',
        ]);
        $this->paso = $this->insertar('procedures', [
            'company_uuid' => $this->empresa,
            'parent_procedure_uuid' => $this->expediente,
            'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'TARJETA_DE_OPERACION',
            'procedure_code' => 'EXP-CONS-1-TAR',
            'status' => 'EN_PROCESO',
        ]);
    }

    private function admin(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prueba',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $rol = Role::findOrCreate('ADMIN_EMPRESA', 'api');
        foreach (['radicacion_to.index', 'radicacion_to.create', 'radicacion_to.update'] as $permiso) {
            $rol->givePermissionTo(Permission::findOrCreate($permiso, 'api'));
        }
        $user->assignRole($rol);
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function contrato(array $extra = []): array
    {
        return array_merge([
            'company_uuid' => $this->empresa,
            'procedure_uuid' => $this->paso,
            'issue_date' => '2026-11-10',
            'start_date' => '2026-11-10',
            'end_date' => '2027-11-10',
            'duration' => 365,
            'valuation_amount' => 0,
        ], $extra);
    }

    public function test_los_dos_contratos_comparten_el_consecutivo_de_la_empresa(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);
        $base = '/api/v1/procedure';

        $admin = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("$base/fleet-service-contracts", $this->contrato(['type_of_action' => 'M']));
        $admin->assertCreated();
        $this->assertSame('000001', $admin->json('data.contract_number'));
        $this->assertSame('1', $admin->json('data.contract_type'));

        $prest = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("$base/service-provision-contracts", $this->contrato(['type_of_action' => 'E', 'coverage' => 'NACIONAL']));
        $prest->assertCreated();
        $this->assertSame('000002', $prest->json('data.contract_number'));
        $this->assertSame('2', $prest->json('data.contract_type'));
        $this->assertSame('E', $prest->json('data.type_of_action'));

        $this->assertDatabaseHas('fleet_service_contracts', [
            'contract_number' => '000001',
            'contract_type' => '1',
            'type_of_action' => 'M',
        ]);
        $this->assertDatabaseHas('service_provision_contracts', [
            'contract_number' => '000002',
            'contract_type' => '2',
            'type_of_action' => 'E',
        ]);
    }

    public function test_el_consecutivo_es_por_empresa(): void
    {
        $otra = $this->insertar('companies', ['business_name' => 'Otra Empresa', 'is_active' => true]);
        $pasoAjeno = $this->insertar('procedures', [
            'company_uuid' => $otra,
            'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'TARJETA_DE_OPERACION',
            'procedure_code' => 'EXP-OTRA-1',
            'status' => 'EN_PROCESO',
        ]);

        $user = $this->admin();
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $otra,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($user, ['*']);
        $base = '/api/v1/procedure';

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("$base/fleet-service-contracts", $this->contrato())
            ->assertCreated();

        $this->withHeader('X-Company-UUID', $otra)
            ->postJson("$base/service-provision-contracts", $this->contrato([
                'company_uuid' => $otra,
                'procedure_uuid' => $pasoAjeno,
                'coverage' => 'NACIONAL',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.contract_number', '000001');
    }

    public function test_el_detalle_expone_los_datos_de_la_empresa_y_el_proximo_numero(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $res = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson("/api/v1/procedure/radicacion/{$this->expediente}/detalle");

        $res->assertOk()
            ->assertJsonPath('data.empresa.razon_social', 'Transportes Consecutivo S.A.S')
            ->assertJsonPath('data.empresa.direccion', 'Calle 10 # 20-30')
            ->assertJsonPath('data.empresa.telefono', '3001234567')
            ->assertJsonPath('data.empresa.representante_legal', 'Ana Pérez')
            ->assertJsonPath('data.siguiente_contrato', '000001');
    }
}
