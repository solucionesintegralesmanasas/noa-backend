<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\SystemConfigurationService;
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
 * Regresiones detectadas en la revisión de `763e724..HEAD`:
 * - el PUT de contratos devolvía `true` en `data` en vez del registro actualizado;
 * - la configuración mezclaba `corporate_rcc_expiration` con `corporate_rce_expiration`.
 */
class RegresionContratosConfiguracionTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Regresión contratos', 'is_active' => true]);
    }

    private function usuario(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prueba Contratos',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('ADMIN_EMPRESA', 'api'));

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    /** @return array<string, string> */
    private function cabecera(): array
    {
        return ['X-Company-UUID' => $this->empresa];
    }

    public function test_actualizar_contrato_de_flota_devuelve_el_registro(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $contrato = $this->insertar('fleet_service_contracts', [
            'company_uuid' => $this->empresa,
            'contract_number' => 'FC-001',
        ]);

        $response = $this->putJson(
            "/api/v1/procedure/fleet-service-contracts/{$contrato}",
            ['contract_number' => 'FC-002'],
            $this->cabecera()
        );

        $response->assertOk();
        $this->assertIsArray($response->json('data'), 'El PUT devolvió un booleano en vez del registro');
        $this->assertSame('FC-002', $response->json('data.contract_number'));
    }

    public function test_actualizar_contrato_de_prestacion_devuelve_el_registro(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $procedimiento = $this->insertar('procedures', ['company_uuid' => $this->empresa]);
        $contrato = $this->insertar('service_provision_contracts', [
            'company_uuid' => $this->empresa,
            'procedure_uuid' => $procedimiento,
            'contract_number' => 'SP-001',
            'issue_date' => '2026-01-01',
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-31',
            'duration' => 300,
        ]);

        $response = $this->putJson(
            "/api/v1/procedure/service-provision-contracts/{$contrato}",
            ['contract_number' => 'SP-002'],
            $this->cabecera()
        );

        $response->assertOk();
        $this->assertIsArray($response->json('data'), 'El PUT devolvió un booleano en vez del registro');
        $this->assertSame('SP-002', $response->json('data.contract_number'));
    }

    public function test_la_configuracion_no_mezcla_rcc_con_rce(): void
    {
        $config = app(SystemConfigurationService::class)->createSystemConfiguration([
            'company_uuid' => $this->empresa,
            'corporate_rcc_expiration' => '2026-11-01',
            'corporate_rce_expiration' => '2026-12-01',
        ]);

        $fila = DB::table('system_configuration')->where('uuid', $config->uuid)->first();
        $this->assertSame('2026-11-01', $fila->corporate_rcc_expiration);
        $this->assertSame('2026-12-01', $fila->corporate_rce_expiration);
    }

    public function test_actualizar_solo_rce_no_pisa_rcc(): void
    {
        $servicio = app(SystemConfigurationService::class);
        $config = $servicio->createSystemConfiguration([
            'company_uuid' => $this->empresa,
            'corporate_rcc_expiration' => '2026-11-01',
            'corporate_rce_expiration' => '2026-12-01',
        ]);

        $servicio->updateSystemConfiguration($config->uuid, ['corporate_rce_expiration' => '2027-01-01']);

        $fila = DB::table('system_configuration')->where('uuid', $config->uuid)->first();
        $this->assertSame('2026-11-01', $fila->corporate_rcc_expiration);
        $this->assertSame('2027-01-01', $fila->corporate_rce_expiration);
    }
}
