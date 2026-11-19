<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Relevo de conductor en el proyecto: suma al entrante al vehículo como un
 * turno más y conserva vigente al conductor anterior. Nunca crea ni cierra
 * planillas de control.
 */
class RelevoPlanillaTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $empresaB;

    private string $proyecto;

    private string $vehiculo;

    private string $vehiculoB;

    private string $saliente;

    private string $entrante;

    private string $terceroB;

    private User $admin;

    private User $sinPermiso;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresa = $this->crearEmpresa('A');
        $this->empresaB = $this->crearEmpresa('B');

        $this->saliente = $this->crearTercero($this->empresa, '91000001', 'Saliente');
        $this->entrante = $this->crearTercero($this->empresa, '91000002', 'Entrante');
        $this->terceroB = $this->crearTercero($this->empresaB, '92000001', 'Otro');

        $this->vehiculo = $this->crearVehiculo($this->empresa, 'ABC001');
        $this->vehiculoB = $this->crearVehiculo($this->empresa, 'ABC002');

        $this->proyecto = $this->crearProyecto($this->empresa, 'Ruta Norte');
        $this->crearProyecto($this->empresaB, 'Ruta Sur');

        $this->admin = $this->crearUsuario($this->empresa, 'admin.relevo@test.local', [
            'service_delivery_control_sheets.update',
            'projects.update',
        ]);
        $this->sinPermiso = $this->crearUsuario($this->empresa, 'sin.relevo@test.local', [
            'service_delivery_control_sheets.update',
        ]);
    }

    private function crearEmpresa(string $sufijo): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.$sufijo.' '.Str::random(4), 'municipality_uuid' => $relleno,
            'address' => 'Calle 1', 'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno,
            'web_page' => 'https://e.test', 'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function crearTercero(string $empresa, string $documento, string $apellido): string
    {
        return $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => $documento,
            'first_name' => 'CONDUCTOR',
            'last_name' => $apellido,
            'email' => 'tp.'.Str::lower($apellido).Str::random(3).'@test.local',
            'is_driver' => 1,
        ]);
    }

    private function crearVehiculo(string $empresa, string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => 1,
        ]);
    }

    private function crearProyecto(string $empresa, string $nombre): string
    {
        return $this->insertar('projects', [
            'company_uuid' => $empresa,
            'project_name' => $nombre,
            'start_date' => '2026-01-01',
            'completion_date' => '2026-12-31',
        ]);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function crearUsuario(string $empresa, string $email, array $permisos): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.Str::random(4),
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        foreach ($permisos as $permiso) {
            $user->givePermissionTo(Permission::findOrCreate($permiso, 'api'));
        }

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function asignar(string $conductor, string $vehiculo, bool $activa = true): void
    {
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $this->proyecto,
            'third_party_uuid' => $conductor,
            'vehicle_uuid' => $vehiculo,
            'is_active' => $activa ? 1 : 0,
        ]);
    }

    /** Crea una hoja abierta de un día con su control interno. */
    private function crearHojaAbierta(string $conductor, string $vehiculo): string
    {
        $uuid = $this->insertar('service_delivery_control_sheet', [
            'company_uuid' => $this->empresa,
            'project_uuid' => $this->proyecto,
            'service_date' => Carbon::today()->toDateString(),
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->toDateString(),
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'is_active' => 1,
            'start_time' => '07:00:00',
            'starting_kilometer' => '45000',
            'official_name_and_surname' => 'CONDUCTOR Saliente',
        ]);

        $this->insertar('service_internal_controls', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculo,
            'third_party_uuid' => $conductor,
            'service_delivery_control_sheet_uuid' => $uuid,
            'is_active' => 1,
        ]);

        return $uuid;
    }

    private function comoAdmin()
    {
        Sanctum::actingAs($this->admin, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresa]);
    }

    private function comoSinPermiso()
    {
        Sanctum::actingAs($this->sinPermiso, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresa]);
    }

    /** @return array<string, mixed> */
    private function relevo(string $conductor): array
    {
        return [
            'company_uuid' => $this->empresa,
            'project_uuid' => $this->proyecto,
            'vehicle_uuid' => $this->vehiculo,
            'third_party_uuid' => $conductor,
        ];
    }

    private function activaDe(string $conductor, string $vehiculo): int
    {
        return (int) DB::table('project_driver_vehicles')
            ->where('project_uuid', $this->proyecto)
            ->where('third_party_uuid', $conductor)
            ->where('vehicle_uuid', $vehiculo)
            ->value('is_active');
    }

    public function test_relevo_activa_al_entrante_y_conserva_al_saliente(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);
        $this->asignar($this->entrante, $this->vehiculo, false);

        $respuesta = $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(201);

        $this->assertSame($this->entrante, $respuesta->json('data.third_party_uuid'));
        $this->assertSame($this->saliente, $respuesta->json('data.saliente_uuid'));

        // El relevo es un turno más: el conductor anterior sigue vigente en el proyecto.
        $this->assertSame(1, $this->activaDe($this->saliente, $this->vehiculo));
        $this->assertSame(1, $this->activaDe($this->entrante, $this->vehiculo));
    }

    public function test_relevo_conserva_la_planilla_del_saliente_y_crea_la_del_entrante(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);

        $hojaSaliente = $this->crearHojaAbierta($this->saliente, $this->vehiculo);

        $respuesta = $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(201);

        // La planilla del turno saliente sigue abierta y sin hora/km de cierre.
        $saliente = DB::table('service_delivery_control_sheet')->where('uuid', $hojaSaliente);
        $this->assertSame(1, (int) $saliente->value('is_active'));
        $this->assertNull($saliente->value('end_time'));
        $this->assertNull($saliente->value('ending_kilometer'));
        $this->assertSame(
            $this->saliente,
            DB::table('service_internal_controls')->where('service_delivery_control_sheet_uuid', $hojaSaliente)->value('third_party_uuid')
        );

        // El entrante recibe su propia hoja de control del día.
        $hojaEntrante = $respuesta->json('data.sheet_uuid');
        $this->assertNotSame($hojaSaliente, $hojaEntrante);

        $nueva = DB::table('service_delivery_control_sheet')->where('uuid', $hojaEntrante);
        $this->assertSame(1, (int) $nueva->value('is_active'));
        $this->assertSame(Carbon::today()->toDateString(), Carbon::parse($nueva->value('service_date'))->toDateString());
        $this->assertSame(2, DB::table('service_delivery_control_sheet')->count());
        $this->assertSame(
            $this->entrante,
            DB::table('service_internal_controls')->where('service_delivery_control_sheet_uuid', $hojaEntrante)->value('third_party_uuid')
        );
    }

    public function test_relevo_no_duplica_la_hoja_del_entrante(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);

        // El entrante ya tenía su planilla abierta de hoy para ese coche.
        $hojaEntrante = $this->crearHojaAbierta($this->entrante, $this->vehiculo);

        $respuesta = $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(201);

        $this->assertSame($hojaEntrante, $respuesta->json('data.sheet_uuid'));
        $this->assertSame(1, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_entrante_fuera_del_proyecto_se_asigna_automaticamente(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);

        $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(201);

        $this->assertSame(1, $this->activaDe($this->entrante, $this->vehiculo));
    }

    public function test_entrante_activo_en_otro_vehiculo_responde_422(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);
        $this->asignar($this->entrante, $this->vehiculoB, true);

        $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(422);

        // Sin cambios: el saliente sigue activo.
        $this->assertSame(1, $this->activaDe($this->saliente, $this->vehiculo));
    }

    public function test_entrante_de_otra_empresa_responde_422(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);

        $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->terceroB)
        )->assertStatus(422);
    }

    public function test_entrante_ya_asignado_al_vehiculo_responde_422(): void
    {
        $this->asignar($this->entrante, $this->vehiculo, true);

        $respuesta = $this->comoAdmin()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(422);

        $this->assertContains('third_party_uuid', collect($respuesta->json('error.details'))->pluck('field')->all());
    }

    public function test_sin_permiso_projects_update_responde_403(): void
    {
        $this->asignar($this->saliente, $this->vehiculo, true);

        $this->comoSinPermiso()->postJson(
            '/api/v1/control-sheets/service-delivery-control-sheets/relay',
            $this->relevo($this->entrante)
        )->assertStatus(403);
    }
}
