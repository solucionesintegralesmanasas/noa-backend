<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
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

class ReporteActividadVehicularTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresaUuid;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10');

        $this->empresaUuid = $this->insertar('companies', [
            'business_name' => 'TRANSPORTES SIN BARRERAS S.A.S',
            'trade_name' => 'TSB S.A.S',
            'is_active' => true,
        ]);

        $this->insertar('system_configuration', [
            'company_uuid' => $this->empresaUuid,
            'own_company_names' => json_encode(['TRANSPORTES SIN BARRERAS SAS']),
        ]);

        request()->attributes->set('current_company_uuid', $this->empresaUuid);

        Schema::disableForeignKeyConstraints();
        $this->user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Reportes',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $perm = Permission::findOrCreate('reports.vehicle_activity.index', 'api');
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

    // -------------------------------------------------- helpers de semilla

    private function vehiculo(string $placa, ?string $empresa = null): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ]);
    }

    private function inspeccion(string $fecha, string $vehicle, ?string $empresa = null): string
    {
        return $this->insertar('vehicle_inspections', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'vehicle_uuid' => $vehicle,
            'inspection_date' => $fecha,
            'mileage' => 45000,
        ]);
    }

    private function mantenimiento(string $fecha, string $vehicle, ?string $empresa = null): string
    {
        return $this->insertar('maintenance', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'vehicle_uuid' => $vehicle,
            'maintenance_type' => 'PREVENTIVA',
            'mileage' => 45100,
            'service_description' => 'Cambio de aceite y filtros',
            'maintenance_date' => $fecha,
            'status' => 'Finalizado',
        ]);
    }

    private function combustible(string $fecha, string $vehicle, ?string $empresa = null): string
    {
        return $this->insertar('fuel_logs', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'vehicle_uuid' => $vehicle,
            'date' => $fecha,
            'total_spent' => 410,
        ]);
    }

    private function tanqueo(string $fecha, string $vehicle, ?string $empresa = null): string
    {
        return $this->insertar('fuel_requests', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'vehicle_uuid' => $vehicle,
            'status' => 'PENDIENTE',
            'requested_at' => $fecha.' 08:00:00',
            'code' => Str::random(12),
            'public_token' => Str::random(24),
        ]);
    }

    private function hojaInterna(string $fecha, string $vehicle, ?string $empresa = null, ?string $project = null): string
    {
        $hoja = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'service_date' => $fecha,
            'project_uuid' => $project,
            'official_name_and_surname' => 'Juan Pérez',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
        ]);

        $this->insertar('service_internal_controls', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'vehicle_uuid' => $vehicle,
            'service_delivery_control_sheet_uuid' => $hoja,
        ]);

        return $hoja;
    }

    private function hojaSubcontratada(string $fecha, string $placa, ?string $empresa = null): string
    {
        $hoja = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'service_date' => $fecha,
            'official_name_and_surname' => 'Pedro Gómez',
            'type_of_control_sheet' => 'SUBCONTRATADO',
        ]);

        // Esta tabla no tiene company_uuid: la empresa sale de la hoja padre.
        $this->insertar('service_internal_controls_subcontracted', [
            'service_delivery_control_sheet_uuid' => $hoja,
            'vehicle_license_plate' => $placa,
            'driver_name_and_surname' => 'Pedro Gómez',
        ]);

        return $hoja;
    }

    private function asignarProyecto(string $project, string $vehicle): void
    {
        // Esta tabla no tiene company_uuid: la empresa la resuelven sus FK.
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $project,
            'vehicle_uuid' => $vehicle,
            'third_party_uuid' => (string) Str::uuid(),
            'is_active' => 1,
        ]);
    }

    private function proyecto(string $nombre, ?string $empresa = null): string
    {
        return $this->insertar('projects', [
            'company_uuid' => $empresa ?? $this->empresaUuid,
            'project_name' => $nombre,
        ]);
    }

    private function consultar(array $params): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Company-UUID' => $this->empresaUuid])
            ->getJson('/api/v1/reports/vehicle-activity?'.http_build_query($params));
    }

    private function filas(\Illuminate\Testing\TestResponse $response): array
    {
        return $response->json('data.data') ?? [];
    }

    // --------------------------------------------------------------- tests

    public function test_consolidado_incluye_los_cinco_tipos_en_rango_de_fechas(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v = $this->vehiculo('ABC123');
        $this->inspeccion('2026-11-05', $v);
        $this->mantenimiento('2026-11-05', $v);
        $this->combustible('2026-11-05', $v);
        $this->tanqueo('2026-11-05', $v);
        $this->hojaInterna('2026-11-05', $v);

        $response = $this->consultar(['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30']);

        $response->assertOk()->assertJsonPath('success', true);

        $filas = $this->filas($response);
        $this->assertCount(5, $filas);
        $this->assertEqualsCanonicalizing(
            ['INSPECCION', 'MANTENIMIENTO', 'COMBUSTIBLE', 'TANQUEO', 'HOJA_CONTROL'],
            array_column($filas, 'event_type')
        );
        foreach ($filas as $fila) {
            $this->assertSame('ABC123', $fila['vehicle_license_plate']);
            $this->assertSame('2026-11-05', $fila['event_date']);
            $this->assertNotEmpty($fila['event_uuid']);
        }
        $hoja = collect($filas)->firstWhere('event_type', 'HOJA_CONTROL');
        $this->assertSame('Juan Pérez', $hoja['driver_name']);
        $this->assertSame('2026-11-05', $hoja['event_date']);
    }

    public function test_sin_fechas_o_con_rango_invalido_responde_422(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $sinFechas = $this->consultar([]);
        $sinFechas->assertStatus(422)->assertJsonStructure(['error' => ['details']]);
        $campos = array_column($sinFechas->json('error.details'), 'field');
        $this->assertContains('fecha_desde', $campos);
        $this->assertContains('fecha_hasta', $campos);

        $rangoInvertido = $this->consultar(['fecha_desde' => '2026-11-10', 'fecha_hasta' => '2026-11-01']);
        $rangoInvertido->assertStatus(422)->assertJsonStructure(['error' => ['details']]);
        $campos = array_column($rangoInvertido->json('error.details'), 'field');
        $this->assertContains('fecha_hasta', $campos);
    }

    public function test_las_fechas_acotan_cada_tipo_segun_su_columna(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v = $this->vehiculo('ABC123');
        $this->inspeccion('2026-10-15', $v);
        $this->mantenimiento('2026-10-15', $v);
        $this->combustible('2026-10-15', $v);
        $this->tanqueo('2026-10-15', $v);
        $this->hojaInterna('2026-10-15', $v);

        $fueraDeRango = $this->consultar(['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30']);
        $fueraDeRango->assertOk();
        $this->assertSame(0, $fueraDeRango->json('data.total'));
        $this->assertSame([], $this->filas($fueraDeRango));

        $dentroDeRango = $this->consultar(['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']);
        $dentroDeRango->assertOk();
        $this->assertSame(5, $dentroDeRango->json('data.total'));
    }

    public function test_filtro_por_vehiculo_acota_todos_los_tipos(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v1 = $this->vehiculo('ABC001');
        $v2 = $this->vehiculo('DEF002');
        $this->inspeccion('2026-11-05', $v1);
        $this->mantenimiento('2026-11-05', $v1);
        $this->combustible('2026-11-05', $v2);
        $this->tanqueo('2026-11-05', $v2);
        $this->hojaInterna('2026-11-05', $v1);

        $response = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'vehicle_uuid' => $v1,
        ]);

        $response->assertOk();
        $filas = $this->filas($response);
        $this->assertCount(3, $filas);
        foreach ($filas as $fila) {
            $this->assertSame($v1, $fila['vehicle_uuid']);
            $this->assertSame('ABC001', $fila['vehicle_license_plate']);
        }
    }

    public function test_filtro_por_proyecto_por_pivote_y_por_hoja_directa(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v1 = $this->vehiculo('ABC001');
        $p1 = $this->proyecto('Proyecto Norte');
        $p2 = $this->proyecto('Proyecto Sur');
        $this->asignarProyecto($p1, $v1);

        $this->inspeccion('2026-11-05', $v1);
        $this->mantenimiento('2026-11-05', $v1);
        $this->combustible('2026-11-05', $v1);
        $this->hojaInterna('2026-11-05', $v1, null, $p2);

        $porPivote = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'project_uuid' => $p1,
        ]);
        $porPivote->assertOk();
        $filasPivote = $this->filas($porPivote);
        $this->assertCount(3, $filasPivote);
        $this->assertNotContains('HOJA_CONTROL', array_column($filasPivote, 'event_type'));
        foreach ($filasPivote as $fila) {
            $this->assertSame($p1, $fila['project_uuid']);
            $this->assertSame('Proyecto Norte', $fila['project_name']);
        }

        $porHojaDirecta = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'project_uuid' => $p2,
        ]);
        $porHojaDirecta->assertOk();
        $filasHoja = $this->filas($porHojaDirecta);
        $this->assertCount(1, $filasHoja);
        $this->assertSame('HOJA_CONTROL', $filasHoja[0]['event_type']);
        $this->assertSame('Proyecto Sur', $filasHoja[0]['project_name']);
    }

    public function test_filtro_por_vehiculo_cubre_hojas_internas_y_subcontratadas(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v1 = $this->vehiculo('ABC123');
        $this->hojaInterna('2026-11-05', $v1);
        $this->hojaSubcontratada('2026-11-06', 'ABC123');

        $response = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'vehicle_uuid' => $v1,
        ]);

        $response->assertOk();
        $filas = $this->filas($response);
        $this->assertCount(2, $filas);
        foreach ($filas as $fila) {
            $this->assertSame('HOJA_CONTROL', $fila['event_type']);
            $this->assertSame($v1, $fila['vehicle_uuid']);
        }
    }

    public function test_las_hojas_multidia_no_duplican_al_padre(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v = $this->vehiculo('ABC123');
        $padre = $this->hojaInterna('2026-11-06', $v);

        foreach (['2026-11-06', '2026-11-07'] as $dia) {
            $hija = $this->insertar('service_delivery_control_sheet', [
                'company_uuid' => $this->empresaUuid,
                'service_date' => $dia,
                'parent_uuid' => $padre,
                'official_name_and_surname' => 'Juan Pérez',
                'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            ]);
            $this->insertar('service_internal_controls', [
                'company_uuid' => $this->empresaUuid,
                'vehicle_uuid' => $v,
                'service_delivery_control_sheet_uuid' => $hija,
            ]);
        }

        $response = $this->consultar(['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30']);

        $response->assertOk();
        $filas = $this->filas($response);
        $this->assertCount(2, $filas);
        // Orden descendente: primero el día 07 y después el 06; el padre no aparece.
        $this->assertSame(['2026-11-07', '2026-11-06'], array_column($filas, 'event_date'));
        $this->assertNotContains($padre, array_column($filas, 'event_uuid'));
    }

    public function test_no_incluye_actividad_de_otra_empresa(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otra = $this->insertar('companies', [
            'business_name' => 'OTRA EMPRESA S.A.S',
            'trade_name' => 'OTRA S.A.S',
            'is_active' => true,
        ]);
        $vOtro = $this->vehiculo('ZZZ999', $otra);
        $this->inspeccion('2026-11-05', $vOtro, $otra);
        $this->mantenimiento('2026-11-05', $vOtro, $otra);
        $this->hojaInterna('2026-11-05', $vOtro, $otra);

        $sinFiltros = $this->consultar(['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-30']);
        $sinFiltros->assertOk();
        $this->assertSame(0, $sinFiltros->json('data.total'));

        $conFiltroVehiculo = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'vehicle_uuid' => $vOtro,
        ]);
        $conFiltroVehiculo->assertOk();
        $this->assertSame(0, $conFiltroVehiculo->json('data.total'));
    }

    public function test_las_rutas_exigen_sus_permisos(): void
    {
        Schema::disableForeignKeyConstraints();
        $sinPermiso = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sin Permisos',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        Sanctum::actingAs($sinPermiso, ['*']);

        $this->withHeaders(['X-Company-UUID' => $this->empresaUuid])
            ->getJson('/api/v1/reports/vehicle-activity?fecha_desde=2026-11-01&fecha_hasta=2026-11-30')
            ->assertForbidden();
    }

    public function test_export_excel_entrega_xlsx_y_respeta_su_permiso(): void
    {
        $this->user->givePermissionTo(
            Permission::findOrCreate('reports.vehicle_activity.export-excel', 'api')
        );
        Sanctum::actingAs($this->user, ['*']);

        $v = $this->vehiculo('ABC123');
        $this->mantenimiento('2026-11-05', $v);

        $response = $this->withHeaders(['X-Company-UUID' => $this->empresaUuid])
            ->get('/api/v1/reports/vehicle-activity/excel?fecha_desde=2026-11-01&fecha_hasta=2026-11-30');

        $response->assertOk();
        $disposicion = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('Reporte_Actividad_Vehicular_', $disposicion);
        $this->assertStringContainsString('.xlsx', $disposicion);

        // Quién tiene solo index (sin export-excel) no puede exportar.
        Schema::disableForeignKeyConstraints();
        $sinExport = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Solo Index',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        $sinExport->givePermissionTo(Permission::findOrCreate('reports.vehicle_activity.index', 'api'));

        Sanctum::actingAs($sinExport, ['*']);
        $this->withHeaders(['X-Company-UUID' => $this->empresaUuid])
            ->get('/api/v1/reports/vehicle-activity/excel?fecha_desde=2026-11-01&fecha_hasta=2026-11-30')
            ->assertForbidden();
    }

    public function test_export_pdf_entrega_archivo_pdf_y_respeta_su_permiso(): void
    {
        $this->user->givePermissionTo(
            Permission::findOrCreate('reports.vehicle_activity.export-pdf', 'api')
        );
        Sanctum::actingAs($this->user, ['*']);

        $v = $this->vehiculo('XYZ789');
        $this->mantenimiento('2026-11-06', $v);

        $response = $this->withHeaders(['X-Company-UUID' => $this->empresaUuid])
            ->get('/api/v1/reports/vehicle-activity/pdf?fecha_desde=2026-11-01&fecha_hasta=2026-11-30');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $disposicion = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('.pdf', $disposicion);

        // Quién tiene solo index (sin export-pdf) no puede exportar.
        Schema::disableForeignKeyConstraints();
        $sinExport = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Solo Index Pdf',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        $sinExport->givePermissionTo(Permission::findOrCreate('reports.vehicle_activity.index', 'api'));

        Sanctum::actingAs($sinExport, ['*']);
        $this->withHeaders(['X-Company-UUID' => $this->empresaUuid])
            ->get('/api/v1/reports/vehicle-activity/pdf?fecha_desde=2026-11-01&fecha_hasta=2026-11-30')
            ->assertForbidden();
    }

    public function test_la_paginacion_no_repita_ni_omite_filas(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $v = $this->vehiculo('ABC123');
        for ($i = 0; $i < 12; $i++) {
            // Misma fecha a propósito: ejerce el desempate por event_uuid.
            $this->mantenimiento('2026-11-05', $v);
        }

        $pagina1 = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'per_page' => 5,
            'page' => 1,
        ]);
        $pagina2 = $this->consultar([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'per_page' => 5,
            'page' => 2,
        ]);

        $pagina1->assertOk();
        $pagina2->assertOk();
        $this->assertSame(12, $pagina1->json('data.total'));
        $this->assertCount(5, $this->filas($pagina1));
        $this->assertCount(5, $this->filas($pagina2));

        $uuids = array_merge(
            array_column($this->filas($pagina1), 'event_uuid'),
            array_column($this->filas($pagina2), 'event_uuid')
        );
        $this->assertCount(10, array_unique($uuids), 'Las páginas 1 y 2 deben ser disjuntas');
    }
}
