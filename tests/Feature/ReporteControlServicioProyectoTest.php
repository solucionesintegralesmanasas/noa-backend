<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ServiceDeliveryControlSheet;
use App\Models\User;
use App\Models\Vehicle;
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
    use RefreshDatabase, InsertaFilas;

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
}

