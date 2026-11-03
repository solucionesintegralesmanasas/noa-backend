<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reports\VehicleReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

class ReporteVehiculosFiltrosTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresaUuid;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30');

        $this->empresaUuid = $this->insertar('companies', [
            'business_name' => 'TRANSPORTES SIN BARRERAS S.A.S',
            'trade_name' => 'TSB S.A.S',
            'is_active' => true,
        ]);

        $this->insertar('system_configuration', [
            'company_uuid' => $this->empresaUuid,
            'own_company_names' => json_encode(['TRANSPORTES SIN BARRERAS SAS', 'TSB SAS']),
        ]);

        request()->attributes->set('current_company_uuid', $this->empresaUuid);

        Schema::disableForeignKeyConstraints();
        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin Test',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $perm = Permission::findOrCreate('reports.vehicles.index', 'api');
        $role = Role::findOrCreate('SUPERADMIN', 'api');
        $role->givePermissionTo($perm);
        $this->user->assignRole($role);
        $this->user->givePermissionTo($perm);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_catalogo_de_sedes_retorna_sedes_activas_de_la_empresa(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $sede1 = $this->insertar('branches', [
            'company_uuid' => $this->empresaUuid,
            'name' => 'Sede Cali Principal',
            'status' => true,
        ]);

        $sede2 = $this->insertar('branches', [
            'company_uuid' => $this->empresaUuid,
            'name' => 'Sede Yumbo',
            'status' => true,
        ]);

        $response = $this->withHeaders([
            'X-Company-UUID' => $this->empresaUuid,
        ])->getJson('/api/v1/reports/vehicles/catalogs/branches');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['label' => 'Sede Cali Principal', 'value' => $sede1])
            ->assertJsonFragment(['label' => 'Sede Yumbo', 'value' => $sede2]);
    }

    public function test_filtro_por_sede_acota_vehiculos_asociados(): void
    {
        $sede1 = $this->insertar('branches', ['company_uuid' => $this->empresaUuid, 'name' => 'Sede Norte', 'status' => true]);
        $sede2 = $this->insertar('branches', ['company_uuid' => $this->empresaUuid, 'name' => 'Sede Sur', 'status' => true]);

        $v1 = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'NOR001', 'is_active' => true]);
        $v2 = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'SUR002', 'is_active' => true]);

        $this->insertar('vehicles_branches', ['company_uuid' => $this->empresaUuid, 'vehicle_uuid' => $v1, 'branch_uuid' => $sede1, 'is_active' => true]);
        $this->insertar('vehicles_branches', ['company_uuid' => $this->empresaUuid, 'vehicle_uuid' => $v2, 'branch_uuid' => $sede2, 'is_active' => true]);

        $service = app(VehicleReportService::class);

        $resultadoSede1 = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'branch_uuid' => $sede1,
        ]);

        $this->assertSame(1, $resultadoSede1->total());
        $this->assertSame('NOR001', $resultadoSede1->items()[0]['vehicle_license_plate']);
        $this->assertSame('Sede Norte', $resultadoSede1->items()[0]['branch_name']);
    }

    public function test_filtro_por_documento_todos_y_vencimiento(): void
    {
        $vVencido = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'VEN001', 'is_active' => true]);
        $v5Dias = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'PRO002', 'is_active' => true]);
        $vVigente = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'VIG003', 'is_active' => true]);

        // Vencido ayer (2026-10-29)
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vVencido,
            'document_type' => 'SOAT',
            'expiry_date' => '2026-10-29',
            'status' => 'NO VIGENTE',
        ]);

        // Por vencer en 3 días (2026-11-02)
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $v5Dias,
            'document_type' => 'RTM',
            'expiry_date' => '2026-11-02',
            'status' => 'VIGENTE',
        ]);

        // Vigente a 30 días (2026-11-29)
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vVigente,
            'document_type' => 'SOAT',
            'expiry_date' => '2026-11-29',
            'status' => 'VIGENTE',
        ]);

        $service = app(VehicleReportService::class);

        // 1. Filtrar TODOS los documentos en estado 'vencido'
        $resVencidos = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'filter_type' => 'document',
            'document_type' => 'TODOS',
            'doc_status' => 'vencido',
        ]);
        $this->assertSame(1, $resVencidos->total());
        $this->assertSame('VEN001', $resVencidos->items()[0]['vehicle_license_plate']);

        // 2. Filtrar TODOS los documentos en estado 'por_vencer_5d'
        $res5d = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'filter_type' => 'document',
            'document_type' => 'TODOS',
            'doc_status' => 'por_vencer_5d',
        ]);
        $this->assertSame(1, $res5d->total());
        $this->assertSame('PRO002', $res5d->items()[0]['vehicle_license_plate']);

        // 3. Filtrar TODOS los documentos en estado 'mas_15d'
        $resMas15d = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'filter_type' => 'document',
            'document_type' => 'TODOS',
            'doc_status' => 'mas_15d',
        ]);
        $this->assertSame(1, $resMas15d->total());
        $this->assertSame('VIG003', $resMas15d->items()[0]['vehicle_license_plate']);
    }

    public function test_filtro_por_propiedad_propios_vs_terceros(): void
    {
        // Vehículo 1: Particular (siempre propio de la empresa)
        $vPart = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_license_plate' => 'PAR101',
            'type_of_service' => 'PARTICULAR',
            'is_active' => true,
        ]);

        // Vehículo 2: Público con tarjeta propia
        $vProp = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_license_plate' => 'PUB202',
            'type_of_service' => 'PUBLICO',
            'is_active' => true,
        ]);
        $this->insertar('operation_cards', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vProp,
            'affiliated_company' => 'TRANSPORTES SIN BARRERAS S.A.S',
            'operating_card_number' => 'TO-PROPIO',
            'expiration_date' => '2027-01-01',
            'status' => true,
        ]);

        // Vehículo 3: Público de tercero/afiliado (tarjeta de otra empresa)
        $vTerc = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_license_plate' => 'TER303',
            'type_of_service' => 'PUBLICO',
            'is_active' => true,
        ]);
        $this->insertar('operation_cards', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vTerc,
            'affiliated_company' => 'OTRA EMPRESA TRANSPORTADORA S.A.',
            'operating_card_number' => 'TO-TERCERO',
            'expiration_date' => '2027-01-01',
            'status' => true,
        ]);

        // Vehículo 4: Tuvo tarjeta propia en el pasado (2022), pero su tarjeta más reciente (2027) es de un tercero.
        // No debe aparecer como propio por el historial, sino como tercero.
        $vHistorico = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_license_plate' => 'HIS404',
            'type_of_service' => 'PUBLICO',
            'is_active' => true,
        ]);
        $this->insertar('operation_cards', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vHistorico,
            'affiliated_company' => 'TRANSPORTES SIN BARRERAS S.A.S',
            'operating_card_number' => 'TO-VIEJA-PROPIA',
            'expiration_date' => '2022-01-01',
            'status' => false,
        ]);
        $this->insertar('operation_cards', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vHistorico,
            'affiliated_company' => 'TERCERO ACTUAL S.A.',
            'operating_card_number' => 'TO-NUEVA-TERCERO',
            'expiration_date' => '2027-06-01',
            'status' => true,
        ]);

        $service = app(VehicleReportService::class);

        // Propios: PAR101 y PUB202 (HIS404 NO debe entrar porque su tarjeta activa más reciente es de tercero)
        $resPropios = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'ownership_type' => 'propios',
        ]);
        $this->assertSame(2, $resPropios->total());
        $this->assertTrue(collect($resPropios->items())->every(fn ($f) => $f['is_own'] === true && $f['ownership_label'] === 'Propio'));
        $placasPropias = collect($resPropios->items())->pluck('vehicle_license_plate')->all();
        $this->assertContains('PAR101', $placasPropias);
        $this->assertContains('PUB202', $placasPropias);
        $this->assertNotContains('TER303', $placasPropias);
        $this->assertNotContains('HIS404', $placasPropias);

        // Terceros: TER303 y HIS404
        $resTerceros = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'ownership_type' => 'terceros',
        ]);
        $this->assertSame(2, $resTerceros->total());
        $placasTerceros = collect($resTerceros->items())->pluck('vehicle_license_plate')->all();
        $this->assertContains('TER303', $placasTerceros);
        $this->assertContains('HIS404', $placasTerceros);
        $this->assertTrue(collect($resTerceros->items())->every(fn ($f) => $f['is_own'] === false && $f['ownership_label'] === 'Tercero'));
    }

    public function test_la_sede_se_muestra_sin_filtrar_por_sede(): void
    {
        $sede = $this->insertar('branches', ['company_uuid' => $this->empresaUuid, 'name' => 'Sede Centro', 'status' => true]);
        $v = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'CEN001', 'is_active' => true]);
        $this->insertar('vehicles_branches', ['company_uuid' => $this->empresaUuid, 'vehicle_uuid' => $v, 'branch_uuid' => $sede, 'is_active' => true]);

        $res = app(VehicleReportService::class)->paginateReport(['company_uuid' => $this->empresaUuid]);

        $this->assertSame('Sede Centro', $res->items()[0]['branch_name']);
    }

    public function test_filtro_por_convenio_y_vencimiento(): void
    {
        $vConv1 = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'CNV001', 'is_active' => true]);
        $vConv2 = $this->insertar('vehicles', ['company_uuid' => $this->empresaUuid, 'vehicle_license_plate' => 'CNV002', 'is_active' => true]);

        // Convenio que vence hoy (2026-10-30)
        $this->insertar('business_collaboration_agreements', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vConv1,
            'agreement_internal_id' => 'CNV-'.Str::random(8),
            'contracting_entity_name' => 'COOPERATIVA NORTE',
            'expiry_date' => '2026-10-30',
            'status' => true,
        ]);

        // Convenio que vence en 4 días (2026-11-03)
        $this->insertar('business_collaboration_agreements', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_uuid' => $vConv2,
            'agreement_internal_id' => 'CNV-'.Str::random(8),
            'contracting_entity_name' => 'ALCALDIA CALI',
            'expiry_date' => '2026-11-03',
            'status' => true,
        ]);

        $service = app(VehicleReportService::class);

        // Filtrar convenios que vencen hoy
        $resHoy = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'filter_type' => 'agreement',
            'agreement_status' => 'hoy',
        ]);
        $this->assertSame(1, $resHoy->total());
        $this->assertSame('CNV001', $resHoy->items()[0]['vehicle_license_plate']);

        // Filtrar convenios por vencer en 5 días
        $res5d = $service->paginateReport([
            'company_uuid' => $this->empresaUuid,
            'filter_type' => 'agreement',
            'agreement_status' => 'por_vencer_5d',
        ]);
        $this->assertSame(1, $res5d->total());
        $this->assertSame('CNV002', $res5d->items()[0]['vehicle_license_plate']);
    }
}
