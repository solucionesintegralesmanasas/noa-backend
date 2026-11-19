<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

class ReporteControlServicioProyectoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaUuid;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresaUuid = $this->insertar('companies', [
            'business_name' => 'TRANSPORTES SIN BARRERAS S.A.S',
            'trade_name' => 'TSB S.A.S',
            'is_active' => true,
        ]);

        request()->attributes->set('current_company_uuid', $this->empresaUuid);

        Schema::disableForeignKeyConstraints();
        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Reporte PCP',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
        ]);

        $perm = Permission::firstOrCreate(['name' => 'service_delivery_control_sheets.history_pdf', 'guard_name' => 'api']);
        $role = Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'api']);
        $role->givePermissionTo($perm);
        $this->user->assignRole($role);
        $this->user->companies()->attach($this->empresaUuid);
        Schema::enableForeignKeyConstraints();

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_reporte_por_proyecto_requiere_project_uuid_en_pdf(): void
    {
        $response = $this->getJson('/api/v1/control-sheets/service-delivery-control-sheets/reports/proyecto/pdf', [
            'X-Company-UUID' => $this->empresaUuid,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'El reporte por proyecto requiere project_uuid.']);
    }

    public function test_reporte_por_proyecto_requiere_project_uuid_en_excel(): void
    {
        $response = $this->getJson('/api/v1/control-sheets/service-delivery-control-sheets/reports/proyecto/excel', [
            'X-Company-UUID' => $this->empresaUuid,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'El reporte por proyecto requiere project_uuid.']);
    }

    public function test_reporte_por_proyecto_sin_dias_cerrados_devuelve_404(): void
    {
        $projectUuid = (string) Str::uuid();
        $this->insertar('projects', [
            'uuid' => $projectUuid,
            'company_uuid' => $this->empresaUuid,
            'project_name' => 'PROYECTO VACIO',
            'start_date' => '2026-10-01',
            'completion_date' => '2026-10-31',
        ]);

        $response = $this->getJson("/api/v1/control-sheets/service-delivery-control-sheets/reports/proyecto/pdf?project_uuid={$projectUuid}", [
            'X-Company-UUID' => $this->empresaUuid,
        ]);

        $response->assertStatus(404);
    }

    public function test_reporte_por_proyecto_zip_trae_una_planilla_por_vehiculo(): void
    {
        $projectUuid = $this->insertar('projects', [
            'company_uuid' => $this->empresaUuid,
            'project_name' => 'PROYECTO ZIP',
            'start_date' => '2026-11-01',
            'completion_date' => '2026-11-30',
        ]);

        foreach (['AAA111', 'BBB222'] as $placa) {
            $vehicleUuid = $this->insertar('vehicles', [
                'company_uuid' => $this->empresaUuid,
                'vehicle_license_plate' => $placa,
            ]);
            $hojaUuid = $this->insertar('service_delivery_control_sheet', [
                'company_uuid' => $this->empresaUuid,
                'project_uuid' => $projectUuid,
                'service_date' => '2026-11-05',
                'start_date' => '2026-11-05',
                'end_date' => '2026-11-05',
                'is_active' => true,
                'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            ]);
            $this->insertar('service_internal_controls', [
                'company_uuid' => $this->empresaUuid,
                'vehicle_uuid' => $vehicleUuid,
                'service_delivery_control_sheet_uuid' => $hojaUuid,
            ]);
        }

        $response = $this->get(
            "/api/v1/control-sheets/service-delivery-control-sheets/reports/proyecto/zip?project_uuid={$projectUuid}&incluir_abiertos=1&fecha_desde=2026-11-01&fecha_hasta=2026-11-15",
            ['X-Company-UUID' => $this->empresaUuid]
        );

        $response->assertOk()->assertDownload();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $this->assertSame(2, $zip->numFiles, 'El ZIP debe traer una planilla por vehículo.');
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertStringContainsString('AAA111', implode('|', $nombres));
        $this->assertStringContainsString('BBB222', implode('|', $nombres));
    }

    public function test_reporte_zip_con_vehiculo_trae_una_planilla_por_dia(): void
    {
        $projectUuid = $this->insertar('projects', [
            'company_uuid' => $this->empresaUuid,
            'project_name' => 'PROYECTO ZIP DIARIO',
            'start_date' => '2026-11-01',
            'completion_date' => '2026-11-30',
        ]);
        $vehicleUuid = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaUuid,
            'vehicle_license_plate' => 'CCC333',
        ]);

        foreach (['2026-11-01', '2026-11-02', '2026-11-03'] as $dia) {
            $hojaUuid = $this->insertar('service_delivery_control_sheet', [
                'company_uuid' => $this->empresaUuid,
                'project_uuid' => $projectUuid,
                'service_date' => $dia,
                'start_date' => $dia,
                'end_date' => $dia,
                'is_active' => true,
                'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            ]);
            $this->insertar('service_internal_controls', [
                'company_uuid' => $this->empresaUuid,
                'vehicle_uuid' => $vehicleUuid,
                'service_delivery_control_sheet_uuid' => $hojaUuid,
            ]);
        }

        $response = $this->get(
            "/api/v1/control-sheets/service-delivery-control-sheets/reports/proyecto/zip?project_uuid={$projectUuid}&vehicle_uuid={$vehicleUuid}&incluir_abiertos=1&fecha_desde=2026-11-01&fecha_hasta=2026-11-03",
            ['X-Company-UUID' => $this->empresaUuid]
        );

        $response->assertOk()->assertDownload();

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();

        sort($nombres);
        $this->assertSame([
            'Planilla CCC333 - 2026-11-01.pdf',
            'Planilla CCC333 - 2026-11-02.pdf',
            'Planilla CCC333 - 2026-11-03.pdf',
        ], $nombres);
    }
}
