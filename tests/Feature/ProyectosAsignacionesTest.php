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
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Contrato del detalle de proyectos al agregar o alternar asignaciones
 * conductor-vehículo sin enviar el formulario completo.
 *
 * El backend sincroniza por conductor: las filas ausentes se retiran y, si un
 * conductor llega dos veces, gana la última. Por eso el frontend envía solo las
 * activas más la fila que agrega o alterna (ver utils/assignments.js).
 */
class ProyectosAsignacionesTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $proyecto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', [
            'business_name' => 'Proyectos Asignaciones',
            'is_active' => true,
        ]);

        $this->proyecto = $this->insertar('projects', [
            'company_uuid' => $this->empresa,
            'project_name' => 'Ruta Norte',
            'start_date' => '2026-09-30',
            'completion_date' => '2026-12-31',
            'project_value' => 1500000,
        ]);

        Sanctum::actingAs($this->superadmin(), ['*']);
    }

    private function superadmin(): User
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
        $user->assignRole(Role::findOrCreate('SUPERADMIN', 'api'));

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function conductor(string $documento): string
    {
        return $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'first_name' => 'Conductor',
            'last_name' => $documento,
            'document_number' => $documento,
            'is_driver' => 1,
            'is_active' => 1,
        ]);
    }

    private function vehiculo(string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'internal_number' => 'INT-'.$placa,
            'is_active' => 1,
        ]);
    }

    private function asignar(string $conductor, string $vehiculo, bool $activa = true): void
    {
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $this->proyecto,
            'third_party_uuid' => $conductor,
            'vehicle_uuid' => $vehiculo,
            'is_active' => $activa,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignments
     */
    private function actualizarAsignaciones(array $assignments): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Company-UUID', $this->empresa)
            ->putJson("/api/v1/projects/{$this->proyecto}", ['assignments' => $assignments]);
    }

    private function activas(): array
    {
        return DB::table('project_driver_vehicles')
            ->where('project_uuid', $this->proyecto)
            ->where('is_active', true)
            ->pluck('third_party_uuid')
            ->all();
    }

    public function test_agregar_una_asignacion_conserva_las_activas_y_las_fechas(): void
    {
        $d1 = $this->conductor('900000001');
        $v1 = $this->vehiculo('AAA001');
        $d2 = $this->conductor('900000002');
        $v2 = $this->vehiculo('AAA002');
        $d3 = $this->conductor('900000003');
        $v3 = $this->vehiculo('AAA003');

        $this->asignar($d1, $v1);
        $this->asignar($d2, $v2);

        $this->actualizarAsignaciones([
            ['third_party_uuid' => $d1, 'vehicle_uuid' => $v1, 'is_active' => true],
            ['third_party_uuid' => $d2, 'vehicle_uuid' => $v2, 'is_active' => true],
            ['third_party_uuid' => $d3, 'vehicle_uuid' => $v3, 'is_active' => true],
        ])->assertOk();

        $this->assertEqualsCanonicalizing([$d1, $d2, $d3], $this->activas());

        $proyecto = DB::table('projects')->where('uuid', $this->proyecto)->first();
        $this->assertSame('2026-09-30', (string) $proyecto->start_date);
        $this->assertSame('2026-12-31', (string) $proyecto->completion_date);
        $this->assertSame('Ruta Norte', $proyecto->project_name);
    }

    public function test_una_asignacion_retirada_del_mismo_conductor_no_pisa_la_activa(): void
    {
        $d1 = $this->conductor('900000011');
        $v1 = $this->vehiculo('BBB001');
        $v0 = $this->vehiculo('BBB000');
        $d2 = $this->conductor('900000012');
        $v2 = $this->vehiculo('BBB002');
        $d3 = $this->conductor('900000013');
        $v3 = $this->vehiculo('BBB003');

        $this->asignar($d1, $v1);
        // Histórico retirado del mismo conductor: si el payload lo enviara
        // después de la activa, el backend colapsaría y la desactivaría.
        $this->asignar($d1, $v0, false);
        $this->asignar($d2, $v2);

        $this->actualizarAsignaciones([
            ['third_party_uuid' => $d1, 'vehicle_uuid' => $v1, 'is_active' => true],
            ['third_party_uuid' => $d2, 'vehicle_uuid' => $v2, 'is_active' => true],
            ['third_party_uuid' => $d3, 'vehicle_uuid' => $v3, 'is_active' => true],
        ])->assertOk();

        $activaD1 = DB::table('project_driver_vehicles')
            ->where('project_uuid', $this->proyecto)
            ->where('third_party_uuid', $d1)
            ->where('is_active', true)
            ->get();

        $this->assertCount(1, $activaD1);
        $this->assertSame($v1, $activaD1->first()->vehicle_uuid);

        $this->assertDatabaseHas('project_driver_vehicles', [
            'project_uuid' => $this->proyecto,
            'third_party_uuid' => $d1,
            'vehicle_uuid' => $v0,
            'is_active' => false,
        ]);
    }

    public function test_retirar_una_asignacion_conserva_las_demas_activas(): void
    {
        $d1 = $this->conductor('900000021');
        $v1 = $this->vehiculo('CCC001');
        $d2 = $this->conductor('900000022');
        $v2 = $this->vehiculo('CCC002');

        $this->asignar($d1, $v1);
        $this->asignar($d2, $v2);

        $this->actualizarAsignaciones([
            ['third_party_uuid' => $d1, 'vehicle_uuid' => $v1, 'is_active' => true],
            ['third_party_uuid' => $d2, 'vehicle_uuid' => $v2, 'is_active' => false],
        ])->assertOk();

        $this->assertSame([$d1], $this->activas());
        $this->assertDatabaseHas('project_driver_vehicles', [
            'project_uuid' => $this->proyecto,
            'third_party_uuid' => $d2,
            'vehicle_uuid' => $v2,
            'is_active' => false,
        ]);
    }

    public function test_el_mismo_conductor_no_puede_llegar_dos_veces_en_el_payload(): void
    {
        $d1 = $this->conductor('900000031');
        $v1 = $this->vehiculo('DDD001');
        $v0 = $this->vehiculo('DDD000');

        $this->asignar($d1, $v1);

        $this->actualizarAsignaciones([
            ['third_party_uuid' => $d1, 'vehicle_uuid' => $v1, 'is_active' => true],
            ['third_party_uuid' => $d1, 'vehicle_uuid' => $v0, 'is_active' => true],
        ])->assertStatus(422);

        // No se aplicó nada: la asignación original sigue activa.
        $this->assertSame([$d1], $this->activas());
        $this->assertDatabaseMissing('project_driver_vehicles', [
            'project_uuid' => $this->proyecto,
            'vehicle_uuid' => $v0,
        ]);
    }

    public function test_el_detalle_ordena_las_asignaciones_por_ingreso(): void
    {
        $d1 = $this->conductor('900000041');
        $v1 = $this->vehiculo('EEE001');
        $d2 = $this->conductor('900000042');
        $v2 = $this->vehiculo('EEE002');
        $d3 = $this->conductor('900000043');
        $v3 = $this->vehiculo('EEE003');

        $this->asignar($d1, $v1);
        $this->asignar($d2, $v2);

        // Retirar la primera no debe moverla al final: conserva su lugar temporal.
        DB::table('project_driver_vehicles')
            ->where('project_uuid', $this->proyecto)
            ->where('third_party_uuid', $d1)
            ->update(['is_active' => false]);

        // Alta nueva: el servicio la crea al final del historial.
        $this->actualizarAsignaciones([
            ['third_party_uuid' => $d2, 'vehicle_uuid' => $v2, 'is_active' => true],
            ['third_party_uuid' => $d3, 'vehicle_uuid' => $v3, 'is_active' => true],
        ])->assertOk();

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson("/api/v1/projects/{$this->proyecto}")
            ->assertOk();

        $orden = collect($respuesta->json('data.assignments'))->pluck('third_party_uuid')->all();
        $this->assertSame([$d1, $d2, $d3], $orden);
    }

    public function test_el_detalle_expone_relevo_solo_con_turno_abierto(): void
    {
        $d = $this->conductor('900000044');
        $v = $this->vehiculo('EEE004');
        $this->asignar($d, $v);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson("/api/v1/projects/{$this->proyecto}")->assertOk();
        $asignacion = collect($respuesta->json('data.assignments'))->firstWhere('vehicle_uuid', $v);
        $this->assertNull($asignacion['relevo']);

        $hoy = now()->toDateString();
        $hoja = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $this->empresa,
            'project_uuid' => $this->proyecto,
            'service_date' => $hoy,
            'start_date' => $hoy,
            'end_date' => $hoy,
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'is_active' => 1,
            'start_time' => '07:12:00',
            'starting_kilometer' => '45100',
            'turno' => 1,
        ]);
        $this->insertar('service_internal_controls', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $v,
            'third_party_uuid' => $d,
            'service_delivery_control_sheet_uuid' => $hoja,
            'is_active' => 1,
        ]);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson("/api/v1/projects/{$this->proyecto}")->assertOk();
        $asignacion = collect($respuesta->json('data.assignments'))->firstWhere('vehicle_uuid', $v);
        $this->assertSame($hoja, $asignacion['relevo']['planilla_uuid']);
        $this->assertSame(1, $asignacion['relevo']['turno']);
    }
}
