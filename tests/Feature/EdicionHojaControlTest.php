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
 * Edición de hojas de control de servicio (SPEC-009): el mismo formulario de la
 * creación actualiza la hoja, mantiene inmutables el tipo y el estado, y
 * sincroniza proyecto, pareja y fechas con las planillas diarias hijas.
 */
class EdicionHojaControlTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaA;

    private string $empresaB;

    private string $sedeA1;

    private string $sedeB;

    private string $proyectoA1;

    private string $proyectoA2;

    /** @var array<int, string> */
    private array $vehiculos = [];

    /** @var array<int, string> */
    private array $conductores = [];

    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
        $this->sedeA1 = $this->crearSede($this->empresaA, 'Sede A');
        $this->sedeB = $this->crearSede($this->empresaB, 'Sede B');

        for ($i = 1; $i <= 3; $i++) {
            $this->conductores[$i] = $this->crearTercero($this->empresaA, '9100001'.$i, 'T'.$i, $this->sedeA1);
            $this->vehiculos[$i] = $this->crearVehiculo($this->empresaA, $this->conductores[$i], 'MASA0'.$i, $this->sedeA1);
        }

        $this->proyectoA1 = $this->crearProyecto($this->empresaA, 'Proyecto Edición A1', [
            ['conductor' => $this->conductores[1], 'vehicle' => $this->vehiculos[1]],
            ['conductor' => $this->conductores[2], 'vehicle' => $this->vehiculos[2]],
            ['conductor' => $this->conductores[3], 'vehicle' => $this->vehiculos[3]],
        ]);
        // Segundo proyecto de la misma empresa: mover la hoja de proyecto no
        // puede perder su pareja ni dejar de propagarse a las hijas.
        $this->proyectoA2 = $this->crearProyecto($this->empresaA, 'Proyecto Edición A2', [
            ['conductor' => $this->conductores[2], 'vehicle' => $this->vehiculos[2]],
        ]);

        $this->adminA = $this->crearUsuario($this->empresaA, 'ADMIN_EMPRESA', 'admin.edicion@test.local');
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

    private function crearTercero(string $empresa, string $documento, string $sufijo, string $sede): string
    {
        return $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => $documento,
            'first_name' => 'CONDUCTOR',
            'last_name' => $sufijo,
            'email' => 'conductor.'.Str::lower($sufijo).Str::random(3).'@test.local',
            'branch_uuid' => $sede,
            'is_driver' => 1,
        ]);
    }

    private function crearVehiculo(string $empresa, string $tercero, string $placa, string $sede): string
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

    /**
     * @param  array<int, array{conductor: string, vehicle: string}>  $pares
     */
    private function crearProyecto(string $empresa, string $nombre, array $pares): string
    {
        $proyecto = $this->insertar('projects', [
            'company_uuid' => $empresa,
            'project_name' => $nombre,
            'start_date' => '2026-01-01',
            'completion_date' => '2026-12-31',
        ]);

        foreach ($pares as $par) {
            $this->insertar('project_driver_vehicles', [
                'project_uuid' => $proyecto,
                'third_party_uuid' => $par['conductor'],
                'vehicle_uuid' => $par['vehicle'],
                'is_active' => 1,
            ]);
        }

        return $proyecto;
    }

    private function crearUsuario(string $empresa, string $rol, string $email): User
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

        $rolInstancia = Role::findOrCreate($rol, 'api');
        $rolInstancia->givePermissionTo([
            Permission::findOrCreate('service_delivery_control_sheets.create', 'api'),
            Permission::findOrCreate('service_delivery_control_sheets.update', 'api'),
            Permission::findOrCreate('projects.update', 'api'),
        ]);
        $user->assignRole($rolInstancia);
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresa,
            'third_party_uuid' => null,
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

    /** @return array<int, string> */
    private function camposDeError($respuesta): array
    {
        return collect($respuesta->json('error.details'))->pluck('field')->all();
    }

    /** Crea una hoja directa por la vía masiva y devuelve el UUID del padre. */
    private function crearHojaDirecta(int $i = 1, string $inicio = '2026-10-10', string $fin = '2026-10-11'): string
    {
        return $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', [
                'service_date' => $inicio,
                'start_date' => $inicio,
                'end_date' => $fin,
                'project_uuid' => $this->proyectoA1,
                'vehicle_uuids' => [$this->vehiculos[$i]],
            ])
            ->assertCreated()
            ->json('data.sheets.0.uuid');
    }

    private function hijoPorFecha(string $padre, string $fecha): ?object
    {
        return DB::table('service_delivery_control_sheet')
            ->where('parent_uuid', $padre)
            ->where('service_date', $fecha)
            ->first();
    }

    /** @return array<int, string> */
    private function fechasDeHijas(string $padre): array
    {
        return DB::table('service_delivery_control_sheet')
            ->where('parent_uuid', $padre)
            ->orderBy('service_date')
            ->pluck('service_date')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datosEdicion(int $i, array $extra = []): array
    {
        return array_merge([
            'project_uuid' => $this->proyectoA1,
            'vehicle_uuid' => $this->vehiculos[$i],
            'third_party_uuid' => $this->conductores[$i],
            'official_name_and_surname' => 'RESPONSABLE '.$i,
            'service_date' => '2026-10-10',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-11',
        ], $extra);
    }

    private function actualizar(string $uuid, array $datos)
    {
        return $this->comoAdminA()
            ->putJson('/api/v1/control-sheets/service-delivery-control-sheets/'.$uuid, $datos);
    }

    public function test_edita_pareja_proyecto_y_fechas_y_propaga_a_las_hijas_abiertas(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-11');

        $this->actualizar($padre, $this->datosEdicion(2, [
            'project_uuid' => $this->proyectoA2,
            'service_date' => '2026-10-09',
            'start_date' => '2026-10-09',
            'end_date' => '2026-10-11',
        ]))->assertOk();

        $filaPadre = DB::table('service_delivery_control_sheet')->where('uuid', $padre)->first();
        $this->assertSame('2026-10-09', $filaPadre->start_date);
        $this->assertSame('2026-10-11', $filaPadre->end_date);
        // El padre toma como fecha de servicio su primer día.
        $this->assertSame('2026-10-09', $filaPadre->service_date);
        $this->assertSame($this->proyectoA2, $filaPadre->project_uuid);
        $this->assertSame('RESPONSABLE 2', $filaPadre->official_name_and_surname);

        $controlPadre = DB::table('service_internal_controls')
            ->where('service_delivery_control_sheet_uuid', $padre)->first();
        $this->assertSame($this->vehiculos[2], $controlPadre->vehicle_uuid);
        $this->assertSame($this->conductores[2], $controlPadre->third_party_uuid);

        // El día 9 se crea al ampliar el rango; los días 10 y 11 se conservan.
        $this->assertSame(
            ['2026-10-09', '2026-10-10', '2026-10-11'],
            $this->fechasDeHijas($padre),
        );

        $hijas = DB::table('service_delivery_control_sheet')
            ->where('parent_uuid', $padre)->get();
        foreach ($hijas as $hija) {
            $this->assertSame($this->proyectoA2, $hija->project_uuid);
            $this->assertSame('2026-10-09', $hija->start_date);
            $this->assertSame('2026-10-11', $hija->end_date);
            $this->assertSame('RESPONSABLE 2', $hija->official_name_and_surname);

            $controlHija = DB::table('service_internal_controls')
                ->where('service_delivery_control_sheet_uuid', $hija->uuid)->first();
            $this->assertNotNull($controlHija);
            $this->assertSame($this->vehiculos[2], $controlHija->vehicle_uuid);
            $this->assertSame($this->conductores[2], $controlHija->third_party_uuid);
        }
    }

    public function test_no_permite_cambiar_el_tipo_de_hoja(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-10');

        $respuesta = $this->actualizar($padre, $this->datosEdicion(1, [
            'type_of_control_sheet' => 'SUBCONTRATADO',
        ]))->assertStatus(422);

        $this->assertContains('type_of_control_sheet', $this->camposDeError($respuesta));
        $this->assertSame(
            'DIRECTO_CON_LA_EMPRESA',
            DB::table('service_delivery_control_sheet')->where('uuid', $padre)->value('type_of_control_sheet'),
        );
    }

    public function test_is_active_no_se_edita_y_la_hoja_cerrada_rechaza_cambios(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-10');

        // El cuerpo intenta abrir/cerrar la hoja: el campo se ignora.
        $this->actualizar($padre, $this->datosEdicion(1, [
            'is_active' => false,
            'end_date' => '2026-10-10',
        ]))->assertOk();
        $this->assertSame(
            1,
            (int) DB::table('service_delivery_control_sheet')->where('uuid', $padre)->value('is_active'),
        );

        // Cerrada (evidencia congelada, SPEC-002 §7.2): ningún cambio pasa.
        DB::table('service_delivery_control_sheet')->where('uuid', $padre)->update(['is_active' => 0]);

        $respuesta = $this->actualizar($padre, [
            'official_name_and_surname' => 'OTRO RESPONSABLE',
        ])->assertStatus(422);

        $this->assertContains('update', $this->camposDeError($respuesta));
        $this->assertSame(
            'RESPONSABLE 1',
            DB::table('service_delivery_control_sheet')->where('uuid', $padre)->value('official_name_and_surname'),
        );
    }

    public function test_propaga_a_las_hijas_abiertas_y_conserva_las_cerradas(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-12');
        $hijaCerrada = $this->hijoPorFecha($padre, '2026-10-12');
        $this->assertNotNull($hijaCerrada);
        DB::table('service_delivery_control_sheet')
            ->where('uuid', $hijaCerrada->uuid)
            ->update(['is_active' => 0]);

        $this->actualizar($padre, $this->datosEdicion(2, [
            'project_uuid' => $this->proyectoA2,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-13',
        ]))->assertOk();

        // La hija cerrada es evidencia: ni fechas, ni proyecto, ni pareja.
        $cerrada = DB::table('service_delivery_control_sheet')->where('uuid', $hijaCerrada->uuid)->first();
        $this->assertSame('2026-10-12', $cerrada->service_date);
        $this->assertSame('2026-10-10', $cerrada->start_date);
        $this->assertSame('2026-10-12', $cerrada->end_date);
        $this->assertSame($this->proyectoA1, $cerrada->project_uuid);
        $this->assertSame('CONDUCTOR T1', $cerrada->official_name_and_surname);

        $controlCerrado = DB::table('service_internal_controls')
            ->where('service_delivery_control_sheet_uuid', $hijaCerrada->uuid)->first();
        $this->assertSame($this->vehiculos[1], $controlCerrado->vehicle_uuid);
        $this->assertSame($this->conductores[1], $controlCerrado->third_party_uuid);

        // Las abiertas (incluido el día 13 recién creado) toman la pareja y el
        // proyecto del padre.
        $abiertas = DB::table('service_delivery_control_sheet')
            ->where('parent_uuid', $padre)
            ->where('is_active', 1)
            ->orderBy('service_date')
            ->get();
        $this->assertSame(
            ['2026-10-10', '2026-10-11', '2026-10-13'],
            $abiertas->pluck('service_date')->all(),
        );

        foreach ($abiertas as $abierta) {
            $this->assertSame($this->proyectoA2, $abierta->project_uuid);
            $this->assertSame('2026-10-13', $abierta->end_date);
            $control = DB::table('service_internal_controls')
                ->where('service_delivery_control_sheet_uuid', $abierta->uuid)->first();
            $this->assertSame($this->vehiculos[2], $control->vehicle_uuid);
            $this->assertSame($this->conductores[2], $control->third_party_uuid);
        }
    }

    public function test_extiende_y_recorta_el_rango_solo_con_dias_abiertos(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-12');

        // Ampliar crea las planillas de los días nuevos.
        $this->actualizar($padre, $this->datosEdicion(1, [
            'end_date' => '2026-10-14',
        ]))->assertOk();
        $this->assertSame(
            ['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'],
            $this->fechasDeHijas($padre),
        );

        $sobrantes = DB::table('service_delivery_control_sheet')
            ->where('parent_uuid', $padre)
            ->whereIn('service_date', ['2026-10-12', '2026-10-13', '2026-10-14'])
            ->pluck('uuid')
            ->all();

        // Recortar elimina los días abiertos sin evidencia y sus controles.
        $this->actualizar($padre, $this->datosEdicion(1, [
            'end_date' => '2026-10-11',
        ]))->assertOk();

        $this->assertSame(['2026-10-10', '2026-10-11'], $this->fechasDeHijas($padre));
        $this->assertSame('2026-10-11', DB::table('service_delivery_control_sheet')
            ->where('uuid', $padre)->value('end_date'));
        $this->assertSame(0, DB::table('service_internal_controls')
            ->whereIn('service_delivery_control_sheet_uuid', $sobrantes)->count());
        $this->assertSame(0, DB::table('service_delivery_control_sheet')
            ->whereIn('uuid', $sobrantes)->count());
    }

    public function test_no_recorta_un_dia_con_evidencia_registrada(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-12');
        $hija = $this->hijoPorFecha($padre, '2026-10-12');
        $this->assertNotNull($hija);
        DB::table('service_delivery_control_sheet')
            ->where('uuid', $hija->uuid)
            ->update(['starting_kilometer' => '120']);

        $respuesta = $this->actualizar($padre, $this->datosEdicion(1, [
            'end_date' => '2026-10-11',
        ]))->assertStatus(422);

        $this->assertContains('end_date', $this->camposDeError($respuesta));
        // La transacción revierte: ni el rango ni el día tocado cambian.
        $this->assertSame('2026-10-12', DB::table('service_delivery_control_sheet')
            ->where('uuid', $padre)->value('end_date'));
        $this->assertNotNull($this->hijoPorFecha($padre, '2026-10-12'));
    }

    public function test_edita_una_hoja_externa_y_actualiza_su_control_con_fuec(): void
    {
        $clase = $this->insertar('vehicle_class', ['description' => 'CAMIONETA']);
        $otraClase = $this->insertar('vehicle_class', ['description' => 'BUS']);
        $fuec = $this->insertar('fuec', [
            'company_uuid' => $this->empresaA,
            'issue_date' => '2026-10-01',
            'request_number' => 'SOL-EDIT-1',
            'number_fuec' => 'F1',
        ]);
        $otroFuec = $this->insertar('fuec', [
            'company_uuid' => $this->empresaA,
            'issue_date' => '2026-10-02',
            'request_number' => 'SOL-EDIT-2',
            'number_fuec' => 'F2',
        ]);

        $creada = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets', [
                'service_date' => '2026-10-10',
                'start_date' => '2026-10-10',
                'end_date' => null,
                'project_uuid' => $this->proyectoA1,
                'type_of_control_sheet' => 'SUBCONTRATADO',
                'vehicle_license_plate' => 'EXT001',
                'driver_name_and_surname' => 'CONDUCTOR EXTERNO',
                'driver_license_number' => '111',
                'vehicle_class_uuid' => $clase,
                'fuec_uuid' => $fuec,
            ])
            ->assertCreated()
            ->json('data');

        $subcontrado = DB::table('service_internal_controls_subcontracted')
            ->where('service_delivery_control_sheet_uuid', $creada['uuid'])->first();
        $this->assertNotNull($subcontrado);
        $this->assertSame($fuec, $subcontrado->fuec_uuid);

        $this->actualizar($creada['uuid'], [
            'service_date' => '2026-10-11',
            'start_date' => '2026-10-11',
            'end_date' => '2026-10-11',
            'vehicle_license_plate' => 'EXT002',
            'driver_name_and_surname' => 'CONDUCTOR NUEVO',
            'driver_license_number' => '222',
            'vehicle_class_uuid' => $otraClase,
            'fuec_uuid' => $otroFuec,
        ])->assertOk();

        $hoja = DB::table('service_delivery_control_sheet')->where('uuid', $creada['uuid'])->first();
        $this->assertSame('2026-10-11', $hoja->service_date);
        $this->assertSame('2026-10-11', $hoja->start_date);
        $this->assertSame('2026-10-11', $hoja->end_date);

        $subcontrado = DB::table('service_internal_controls_subcontracted')
            ->where('service_delivery_control_sheet_uuid', $creada['uuid'])->first();
        $this->assertSame('EXT002', $subcontrado->vehicle_license_plate);
        $this->assertSame('CONDUCTOR NUEVO', $subcontrado->driver_name_and_surname);
        $this->assertSame('222', $subcontrado->driver_license_number);
        $this->assertSame($otraClase, $subcontrado->vehicle_class_uuid);
        $this->assertSame($otroFuec, $subcontrado->fuec_uuid);
    }

    public function test_rechaza_vinculos_de_otra_empresa_sin_tocar_la_hoja(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-10');

        $conductorB = $this->crearTercero($this->empresaB, '93000001', 'B', $this->sedeB);
        $vehiculoB = $this->crearVehiculo($this->empresaB, $conductorB, 'EXTB01', $this->sedeB);
        $proyectoB = $this->crearProyecto($this->empresaB, 'Proyecto B', [
            ['conductor' => $conductorB, 'vehicle' => $vehiculoB],
        ]);

        $respuesta = $this->actualizar($padre, $this->datosEdicion(1, [
            'project_uuid' => $proyectoB,
            'vehicle_uuid' => $vehiculoB,
            'third_party_uuid' => $conductorB,
            'end_date' => '2026-10-10',
        ]))->assertStatus(422);

        $campos = $this->camposDeError($respuesta);
        $this->assertContains('project_uuid', $campos);
        $this->assertContains('vehicle_uuid', $campos);
        $this->assertContains('third_party_uuid', $campos);

        $this->assertSame(
            $this->proyectoA1,
            DB::table('service_delivery_control_sheet')->where('uuid', $padre)->value('project_uuid'),
        );
        $control = DB::table('service_internal_controls')
            ->where('service_delivery_control_sheet_uuid', $padre)->first();
        $this->assertSame($this->vehiculos[1], $control->vehicle_uuid);
        $this->assertSame($this->conductores[1], $control->third_party_uuid);
    }

    public function test_la_actualizacion_parcial_de_una_hija_conserva_su_dia(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-12');
        $hija = $this->hijoPorFecha($padre, '2026-10-11');
        $this->assertNotNull($hija);

        // Este es el payload que envía la vista de la planilla diaria: solo los
        // recorridos. No debe pisar su fecha ni el rango de la hoja padre.
        $this->actualizar($hija->uuid, [
            'routes' => [
                ['order_index' => 0, 'origin' => 'Origen', 'destination' => 'Destino'],
            ],
        ])->assertOk();

        $hijaFresca = DB::table('service_delivery_control_sheet')->where('uuid', $hija->uuid)->first();
        $this->assertSame('2026-10-11', $hijaFresca->service_date);
        $this->assertSame('2026-10-10', $hijaFresca->start_date);
        $this->assertSame('2026-10-12', $hijaFresca->end_date);
        $this->assertSame(1, DB::table('service_delivery_control_sheet_routes')
            ->where('service_delivery_control_sheet_uuid', $hija->uuid)->count());

        $this->assertSame(
            '2026-10-10',
            DB::table('service_delivery_control_sheet')->where('uuid', $padre)->value('start_date'),
        );
    }

    public function test_la_edicion_parcial_de_una_hoja_de_un_dia_no_crea_hija(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-10');
        $this->assertSame([], $this->fechasDeHijas($padre));

        // Igual que el PUT de aislamiento: solo cambia el proyecto. La hoja de
        // un día es su propia planilla y no debe aparecer una hija que la
        // duplique (su control interno exige vehículo y tercero no nulos).
        $this->actualizar($padre, [
            'project_uuid' => $this->proyectoA2,
        ])->assertOk();

        $this->assertSame([], $this->fechasDeHijas($padre));
        $control = DB::table('service_internal_controls')
            ->where('service_delivery_control_sheet_uuid', $padre)->first();
        $this->assertSame($this->vehiculos[1], $control->vehicle_uuid);
        $this->assertSame($this->conductores[1], $control->third_party_uuid);
    }

    public function test_extender_el_rango_hereda_la_pareja_en_las_hijas_nuevas(): void
    {
        $padre = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-10');

        // Edición parcial: el cuerpo no repite vehículo ni conductor. Las
        // hijas nuevas heredan la pareja y el FUEC del control del padre.
        $this->actualizar($padre, [
            'end_date' => '2026-10-11',
        ])->assertOk();

        $this->assertSame(['2026-10-10', '2026-10-11'], $this->fechasDeHijas($padre));

        foreach ($this->fechasDeHijas($padre) as $fecha) {
            $hija = $this->hijoPorFecha($padre, $fecha);
            $control = DB::table('service_internal_controls')
                ->where('service_delivery_control_sheet_uuid', $hija->uuid)->first();
            $this->assertNotNull($control);
            $this->assertSame($this->vehiculos[1], $control->vehicle_uuid);
            $this->assertSame($this->conductores[1], $control->third_party_uuid);
        }
    }

    public function test_actualizacion_masiva_de_proyecto_extiende_todas_las_hojas_incluyendo_cerradas_y_crea_hijas_nuevas(): void
    {
        $padre1 = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-11');
        $padre2 = $this->crearHojaDirecta(2, '2026-10-10', '2026-10-11');
        $padreCerrada = $this->crearHojaDirecta(3, '2026-10-10', '2026-10-11');

        // La hoja 3 ya había cerrado todos sus días previos y por tanto la hoja padre estaba cerrada.
        DB::table('service_delivery_control_sheet')
            ->where('uuid', $padreCerrada)
            ->orWhere('parent_uuid', $padreCerrada)
            ->update(['is_active' => false]);

        $respuesta = $this->comoAdminA()
            ->putJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', [
                'project_uuid' => $this->proyectoA1,
                'end_date' => '2026-10-13',
                'daily_route' => 'Ruta Proyecto Extendido',
            ])
            ->assertOk();

        $this->assertSame(3, $respuesta->json('data.updated'));

        foreach ([$padre1, $padre2, $padreCerrada] as $padre) {
            $filaPadre = DB::table('service_delivery_control_sheet')->where('uuid', $padre)->first();
            $this->assertSame('2026-10-10', $filaPadre->start_date);
            $this->assertSame('2026-10-13', $filaPadre->end_date);
            $this->assertSame('Ruta Proyecto Extendido', $filaPadre->daily_route);
            $this->assertSame(1, (int) $filaPadre->is_active);
            $this->assertSame(
                ['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13'],
                $this->fechasDeHijas($padre),
            );
        }

        // En la hoja que estaba cerrada, los días ya cerrados (10 y 11) siguen cerrados,
        // mientras que los días nuevos (12 y 13) nacen abiertos.
        $this->assertSame(0, (int) $this->hijoPorFecha($padreCerrada, '2026-10-10')->is_active);
        $this->assertSame(0, (int) $this->hijoPorFecha($padreCerrada, '2026-10-11')->is_active);
        $this->assertSame(1, (int) $this->hijoPorFecha($padreCerrada, '2026-10-12')->is_active);
        $this->assertSame(1, (int) $this->hijoPorFecha($padreCerrada, '2026-10-13')->is_active);
    }

    public function test_extender_fechas_del_proyecto_sincroniza_automaticamente_todas_sus_hojas_abiertas(): void
    {
        DB::table('projects')
            ->where('uuid', $this->proyectoA1)
            ->update([
                'start_date' => '2026-10-10',
                'completion_date' => '2026-10-11',
            ]);

        $padre1 = $this->crearHojaDirecta(1, '2026-10-10', '2026-10-11');
        $padre2 = $this->crearHojaDirecta(2, '2026-10-10', '2026-10-11');

        $this->comoAdminA()
            ->putJson('/api/v1/projects/'.$this->proyectoA1, [
                'project_name' => 'Proyecto Edición A1',
                'start_date' => '2026-10-10',
                'completion_date' => '2026-10-14',
                'assignments' => [
                    ['third_party_uuid' => $this->conductores[1], 'vehicle_uuid' => $this->vehiculos[1]],
                    ['third_party_uuid' => $this->conductores[2], 'vehicle_uuid' => $this->vehiculos[2]],
                    ['third_party_uuid' => $this->conductores[3], 'vehicle_uuid' => $this->vehiculos[3]],
                ],
            ])
            ->assertOk();

        foreach ([$padre1, $padre2] as $padre) {
            $filaPadre = DB::table('service_delivery_control_sheet')->where('uuid', $padre)->first();
            $this->assertSame('2026-10-10', $filaPadre->start_date);
            $this->assertSame('2026-10-14', $filaPadre->end_date);
            $this->assertSame(
                ['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'],
                $this->fechasDeHijas($padre),
            );
        }
    }
}
