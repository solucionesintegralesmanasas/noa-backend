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
 * Aislamiento de las planillas de control (PCP): al crearlas o moverlas de
 * proyecto, la empresa, el vehículo, el tercero y el FUEC deben pertenecer a la
 * empresa (y sede) activa.
 *
 * Antes el request solo comprobaba `exists` y el servicio tomaba la empresa del
 * cuerpo: una planilla podía quedar vinculada a un proyecto, un vehículo o un
 * FUEC de otra cuenta.
 */
class PcpAislamientoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaA;

    private string $empresaB;

    private string $sedeA1;

    private string $sedeA2;

    private string $sedeB;

    private string $terceroA1;

    private string $terceroA2;

    private string $terceroB;

    private string $vehiculoA1;

    private string $vehiculoA2;

    private string $vehiculoB;

    private string $proyectoA1;

    private string $proyectoA2;

    private string $proyectoB;

    private string $fuecA;

    private string $fuecB;

    private User $adminA;

    private User $sucursalA1;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
        $this->sedeA1 = $this->crearSede($this->empresaA, 'Sede A1');
        $this->sedeA2 = $this->crearSede($this->empresaA, 'Sede A2');
        $this->sedeB = $this->crearSede($this->empresaB, 'Sede B');

        $this->terceroA1 = $this->crearTercero($this->empresaA, '91000011', 'A1', $this->sedeA1, true);
        $this->terceroA2 = $this->crearTercero($this->empresaA, '91000012', 'A2', $this->sedeA2, true);
        $this->terceroB = $this->crearTercero($this->empresaB, '92000011', 'B', $this->sedeB, true);

        $this->vehiculoA1 = $this->crearVehiculo($this->empresaA, $this->terceroA1, $this->sedeA1, 'PCP01');
        $this->vehiculoA2 = $this->crearVehiculo($this->empresaA, $this->terceroA2, $this->sedeA2, 'PCP02');
        $this->vehiculoB = $this->crearVehiculo($this->empresaB, $this->terceroB, $this->sedeB, 'PCP03');

        $this->proyectoA1 = $this->crearProyecto($this->empresaA, 'Proyecto A1');
        $this->proyectoA2 = $this->crearProyecto($this->empresaA, 'Proyecto A2');
        $this->proyectoB = $this->crearProyecto($this->empresaB, 'Proyecto B');

        // El scope de sede de Project usa sus vehículos asignados: sin esta
        // asignación un usuario SUCURSAL no vería el proyecto aunque el
        // vehículo que va a usar en la planilla sí sea de su sede.
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $this->proyectoA1,
            'third_party_uuid' => $this->terceroA1,
            'vehicle_uuid' => $this->vehiculoA1,
        ]);
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $this->proyectoA2,
            'third_party_uuid' => $this->terceroA2,
            'vehicle_uuid' => $this->vehiculoA2,
        ]);
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $this->proyectoB,
            'third_party_uuid' => $this->terceroB,
            'vehicle_uuid' => $this->vehiculoB,
        ]);

        $this->fuecA = $this->crearFuec($this->empresaA, 'FUEC-A');
        $this->fuecB = $this->crearFuec($this->empresaB, 'FUEC-B');

        $this->adminA = $this->crearUsuario($this->empresaA, 'ADMIN_EMPRESA', 'admin.pcp@test.local', null);
        $this->sucursalA1 = $this->crearUsuario($this->empresaA, 'SUCURSAL', 'sucursal.pcp@test.local', $this->terceroA1);
    }

    private function crearEmpresa(): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.Str::random(5), 'municipality_uuid' => $relleno, 'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno, 'web_page' => 'https://e.test',
            'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function crearSede(string $empresa, string $nombre): string
    {
        $ciudad = $this->insertar('cities', ['name' => 'Ciudad '.$nombre]);

        return $this->insertar('branches', [
            'company_uuid' => $empresa,
            'name' => $nombre,
            'address' => 'Calle '.$nombre,
            'municipality_uuid' => $ciudad,
            'status' => 1,
        ]);
    }

    private function crearTercero(string $empresa, string $documento, string $sufijo, string $sede, bool $conductor): string
    {
        return $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => $documento,
            'first_name' => 'TERCERO',
            'last_name' => $sufijo,
            'email' => 'tercero.'.Str::lower($sufijo).'@test.local',
            'branch_uuid' => $sede,
            'is_driver' => $conductor ? 1 : 0,
        ]);
    }

    private function crearVehiculo(string $empresa, string $tercero, string $sede, string $placa): string
    {
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $empresa,
            'third_party_uuid' => $tercero,
            'vehicle_license_plate' => $placa,
            'is_active' => 1,
        ]);

        $this->insertar('vehicles_branches', [
            'company_uuid' => $empresa,
            'vehicle_uuid' => $vehiculo,
            'branch_uuid' => $sede,
            'entry_date' => '2026-01-01',
            'exit_date' => '2026-01-01',
            'exit_type' => 'ENTRADA',
            'is_active' => 1,
        ]);

        return $vehiculo;
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

    private function crearFuec(string $empresa, string $solicitud): string
    {
        return $this->insertar('fuec', [
            'company_uuid' => $empresa,
            'request_number' => $solicitud,
            'number_fuec' => '1',
            'contract_number_display' => '1',
            'issue_date' => '2026-01-01',
            'effective_date' => '2026-01-01',
            'expiration_date' => '2026-12-31',
            'origin_route' => 'Origen',
            'destination_route' => 'Destino',
            'verification_code' => 'CODIGO-'.$solicitud,
            'status' => 'ACTIVO',
        ]);
    }

    private function crearUsuario(string $empresa, string $rol, string $email, ?string $tercero): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole(Role::findOrCreate($rol, 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresa,
            'third_party_uuid' => $tercero,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function comoAdminA()
    {
        Sanctum::actingAs($this->adminA, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresaA]);
    }

    private function comoSucursalA1()
    {
        Sanctum::actingAs($this->sucursalA1, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresaA]);
    }

    /** @return array<string, mixed> */
    private function datos(string $proyecto, string $vehiculo, string $tercero, ?string $fuec = null): array
    {
        return [
            'service_date' => '2026-10-10',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'project_uuid' => $proyecto,
            'vehicle_uuid' => $vehiculo,
            'third_party_uuid' => $tercero,
            'fuec_uuid' => $fuec,
        ];
    }

    /** @param \Illuminate\Testing\TestResponse $respuesta */
    private function camposDeError($respuesta): array
    {
        return collect($respuesta->json('error.details'))->pluck('field')->all();
    }

    public function test_no_crea_con_un_proyecto_de_otra_empresa(): void
    {
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoB,
                $this->vehiculoA1,
                $this->terceroA1,
            ))
            ->assertStatus(422);

        $this->assertContains('project_uuid', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_no_crea_con_un_vehiculo_de_otra_empresa(): void
    {
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoB,
                $this->terceroA1,
            ))
            ->assertStatus(422);

        $this->assertContains('vehicle_uuid', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_no_crea_con_un_tercero_de_otra_empresa(): void
    {
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoA1,
                $this->terceroB,
            ))
            ->assertStatus(422);

        $this->assertContains('third_party_uuid', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_no_crea_con_un_fuec_de_otra_empresa(): void
    {
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoA1,
                $this->terceroA1,
                $this->fuecB,
            ))
            ->assertStatus(422);

        $this->assertContains('fuec_uuid', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_un_usuario_sucursal_no_crea_con_un_vehiculo_de_otra_sede(): void
    {
        $respuesta = $this->comoSucursalA1()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoA2,
                $this->terceroA2,
            ))
            ->assertStatus(422);

        $this->assertContains('vehicle_uuid', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_un_usuario_sucursal_crea_con_su_vehiculo_sin_enviar_empresa(): void
    {
        $this->comoSucursalA1()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoA1,
                $this->terceroA1,
            ))
            ->assertCreated()
            ->assertJsonPath('data.company_uuid', $this->empresaA);

        $this->assertSame(1, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_no_crea_en_otra_empresa_aunque_la_envie_en_el_cuerpo(): void
    {
        // El header X-Company-UUID define el contexto (A); el cuerpo no puede
        // cambiarlo a B, aunque el usuario conozca ese UUID.
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', array_merge(
                $this->datos($this->proyectoB, $this->vehiculoB, $this->terceroB),
                ['company_uuid' => $this->empresaB],
            ))
            ->assertStatus(422);

        $this->assertContains('company_uuid', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_no_mueve_una_planilla_a_un_proyecto_de_otra_empresa(): void
    {
        $creada = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoA1,
                $this->terceroA1,
            ))
            ->assertCreated()
            ->json('data');

        $respuesta = $this->comoAdminA()
            ->putJson('/api/v1/control-sheets/service-delivery-control-sheets/'.$creada['uuid'], [
                'project_uuid' => $this->proyectoB,
            ])
            ->assertStatus(422);

        $this->assertContains('project_uuid', $this->camposDeError($respuesta));
        $this->assertSame(
            $this->proyectoA1,
            DB::table('service_delivery_control_sheet')->where('uuid', $creada['uuid'])->value('project_uuid'),
        );
    }

    public function test_conserva_la_empresa_del_registro_al_actualizar(): void
    {
        $creada = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', $this->datos(
                $this->proyectoA1,
                $this->vehiculoA1,
                $this->terceroA1,
            ))
            ->assertCreated()
            ->json('data');

        // El cuerpo trae otra empresa, pero el contexto es A (header) y el
        // servicio conserva la empresa del registro: solo cambia el proyecto.
        $this->comoAdminA()
            ->putJson('/api/v1/control-sheets/service-delivery-control-sheets/'.$creada['uuid'], [
                'project_uuid' => $this->proyectoA2,
                'company_uuid' => $this->empresaB,
            ])
            ->assertOk();

        $this->assertSame($this->empresaA, DB::table('service_delivery_control_sheet')
            ->where('uuid', $creada['uuid'])->value('company_uuid'));
        $this->assertSame($this->proyectoA2, DB::table('service_delivery_control_sheet')
            ->where('uuid', $creada['uuid'])->value('project_uuid'));
    }
}
