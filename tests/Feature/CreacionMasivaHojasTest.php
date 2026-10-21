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
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Creación masiva de hojas de control por proyecto (SPEC-009): una hoja por
 * coche del proyecto, conductor y responsable tomados de la asignación
 * vigente, y todo o nada ante cualquier coche sin asignación activa.
 */
class CreacionMasivaHojasTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaA;

    private string $empresaB;

    private string $sedeA1;

    private string $sedeB;

    private string $proyectoA1;

    /** @var array<int, string> UUIDs de vehículos asignados a A1 */
    private array $vehiculos = [];

    /** @var array<int, string> UUIDs de conductores asignados a A1 */
    private array $conductores = [];

    private User $adminA;

    private User $sinPermiso;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
        $this->sedeA1 = $this->crearSede($this->empresaA, 'Sede A');
        $this->sedeB = $this->crearSede($this->empresaB, 'Sede B');

        // Tres pares conductor↔vehículo asignados al proyecto A1.
        for ($i = 1; $i <= 3; $i++) {
            $this->conductores[$i] = $this->crearTercero($this->empresaA, '9100001'.$i, 'T'.$i, $this->sedeA1);
            $this->vehiculos[$i] = $this->crearVehiculo($this->empresaA, $this->conductores[$i], 'MASA0'.$i, $this->sedeA1);
        }
        $this->proyectoA1 = $this->crearProyecto($this->empresaA, 'Proyecto Masivo A1', [
            ['conductor' => $this->conductores[1], 'vehicle' => $this->vehiculos[1]],
            ['conductor' => $this->conductores[2], 'vehicle' => $this->vehiculos[2]],
            ['conductor' => $this->conductores[3], 'vehicle' => $this->vehiculos[3]],
        ]);

        $this->adminA = $this->crearUsuario($this->empresaA, 'ADMIN_EMPRESA', 'admin.bulk@test.local', true);
        $this->sinPermiso = $this->crearUsuario($this->empresaA, 'EMPLEADO', 'empleado.bulk@test.local', false);
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

    private function crearTercero(string $empresa, string $documento, string $sufijo, string $sede, bool $conductor = true): string
    {
        return $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => $documento,
            'first_name' => 'CONDUCTOR',
            'last_name' => $sufijo,
            'email' => 'conductor.'.Str::lower($sufijo).Str::random(3).'@test.local',
            'branch_uuid' => $sede,
            'is_driver' => $conductor ? 1 : 0,
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
     * @param  array<int, array{conductor: string, vehicle: string, is_active?: int}>  $pares
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
                'is_active' => $par['is_active'] ?? 1,
            ]);
        }

        return $proyecto;
    }

    private function crearUsuario(string $empresa, string $rol, string $email, bool $conPermiso): User
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
        if ($conPermiso) {
            $rolInstancia->givePermissionTo(
                Permission::findOrCreate('service_delivery_control_sheets.create', 'api')
            );
        }
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

    private function comoSinPermiso()
    {
        Sanctum::actingAs($this->sinPermiso, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresaA]);
    }

    /** @return array<string, mixed> */
    private function datos(string $proyecto, ?array $coches = null, string $fin = '2026-10-11'): array
    {
        return [
            'service_date' => '2026-10-10',
            'start_date' => '2026-10-10',
            'end_date' => $fin,
            'project_uuid' => $proyecto,
            'vehicle_uuids' => $coches ?? [$this->vehiculos[1], $this->vehiculos[2], $this->vehiculos[3]],
        ];
    }

    /** @param \Illuminate\Testing\TestResponse $respuesta */
    private function camposDeError($respuesta): array
    {
        return collect($respuesta->json('error.details'))->pluck('field')->all();
    }

    public function test_crea_una_hoja_por_asignacion_con_hijos_por_dia(): void
    {
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos($this->proyectoA1))
            ->assertCreated()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.daily_sheets', 6);

        $hojas = $respuesta->json('data.sheets');
        $this->assertCount(3, $hojas);

        // Una hoja padre por coche y dos hijos por coche (día 10 y 11).
        $this->assertSame(3, DB::table('service_delivery_control_sheet')
            ->whereNull('parent_uuid')->where('project_uuid', $this->proyectoA1)->count());
        $this->assertSame(6, DB::table('service_delivery_control_sheet')
            ->whereNotNull('parent_uuid')->count());

        foreach ($this->conductores as $i => $conductor) {
            // El par de la hoja sale de la asignación: ni el cuerpo ni el FUEC
            // (no llegó nada) puede cambiarlo.
            $controlPadre = DB::table('service_internal_controls')
                ->where('vehicle_uuid', $this->vehiculos[$i])
                ->where('is_active', 1)
                ->whereNull('fuec_uuid')
                ->first();
            $this->assertNotNull($controlPadre, "Falta el control interno del coche {$i}");

            $padre = DB::table('service_delivery_control_sheet')
                ->where('uuid', $controlPadre->service_delivery_control_sheet_uuid)->first();
            $this->assertSame('DIRECTO_CON_LA_EMPRESA', $padre->type_of_control_sheet);
            $this->assertSame('CONDUCTOR T'.$i, $padre->official_name_and_surname);
            $this->assertSame('2026-10-10', $padre->start_date);
            $this->assertSame('2026-10-11', $padre->end_date);

            // Cada hijo lleva el vehículo y el responsable de SU asignación.
            $hijos = DB::table('service_internal_controls')
                ->where('vehicle_uuid', $this->vehiculos[$i])
                ->where('is_active', 0)
                ->get();
            $this->assertCount(2, $hijos);
            foreach ($hijos as $hijo) {
                $hoja = DB::table('service_delivery_control_sheet')
                    ->where('uuid', $hijo->service_delivery_control_sheet_uuid)->first();
                $this->assertSame($controlPadre->service_delivery_control_sheet_uuid, $hoja->parent_uuid);
                $this->assertSame('CONDUCTOR T'.$i, $hoja->official_name_and_surname);
                $this->assertContains($hoja->service_date, ['2026-10-10', '2026-10-11']);
            }
        }
    }

    public function test_rechaza_un_coche_de_otro_proyecto_sin_crear_nada(): void
    {
        // Un cuarto coche de la misma empresa, asignado a otro proyecto.
        $conductor4 = $this->crearTercero($this->empresaA, '91000014', 'T4', $this->sedeA1);
        $vehiculo4 = $this->crearVehiculo($this->empresaA, $conductor4, 'MASA04', $this->sedeA1);
        $this->crearProyecto($this->empresaA, 'Proyecto A2', [['conductor' => $conductor4, 'vehicle' => $vehiculo4]]);

        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos(
                $this->proyectoA1,
                [$this->vehiculos[1], $vehiculo4],
            ))
            ->assertStatus(422);

        $this->assertContains('vehicle_uuids', $this->camposDeError($respuesta));

        // Todo o nada: el coche válido tampoco se creó.
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
        $this->assertSame(0, DB::table('service_internal_controls')->count());
    }

    public function test_rechaza_un_coche_con_asignacion_inactiva(): void
    {
        DB::table('project_driver_vehicles')
            ->where('project_uuid', $this->proyectoA1)
            ->where('vehicle_uuid', $this->vehiculos[2])
            ->update(['is_active' => 0]);

        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos($this->proyectoA1))
            ->assertStatus(422);

        $this->assertContains('vehicle_uuids', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_rechaza_un_proyecto_sin_asignaciones(): void
    {
        $vacio = $this->crearProyecto($this->empresaA, 'Proyecto sin asignaciones', []);

        // El coche es de la empresa y pasaría la validación del request, pero el
        // proyecto no lo tiene asignado: mensaje claro y nada creado.
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos(
                $vacio,
                [$this->vehiculos[1]],
            ))
            ->assertStatus(422);

        $this->assertSame(
            1,
            collect($respuesta->json('error.details'))->where('field', 'vehicle_uuids')->count(),
        );
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_un_solo_dia_crea_hojas_sin_hijos(): void
    {
        $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos($this->proyectoA1, fin: '2026-10-10'))
            ->assertCreated()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.daily_sheets', 0);

        $this->assertSame(3, DB::table('service_delivery_control_sheet')->count());
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->whereNotNull('parent_uuid')->count());
    }

    public function test_rechaza_un_tipo_externo(): void
    {
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', array_merge(
                $this->datos($this->proyectoA1),
                ['type_of_control_sheet' => 'SUBCONTRATADO'],
            ))
            ->assertStatus(422);

        $this->assertContains('type_of_control_sheet', $this->camposDeError($respuesta));
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_exige_permiso_de_creacion(): void
    {
        $this->comoSinPermiso()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos($this->proyectoA1))
            ->assertForbidden();

        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
    }

    public function test_un_fallo_a_medias_no_persiste_nada(): void
    {
        // Coche asignado a A1 con conductor de OTRA empresa: la primera hoja se
        // crea bien y la segunda revienta en la validación de pertenencia.
        $terceroB = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaB,
            'person_type' => 'NATURAL',
            'document_number' => '92000001',
            'first_name' => 'CONDUCTOR',
            'last_name' => 'B',
            'email' => 'tercero.b.bulk@test.local',
            'branch_uuid' => $this->sedeB,
            'is_driver' => 1,
        ]);
        $terceroA4 = $this->crearTercero($this->empresaA, '91000014', 'T4', $this->sedeA1, false);
        $vehiculo4 = $this->crearVehiculo($this->empresaA, $terceroA4, 'MASA04', $this->sedeA1);
        $this->insertar('project_driver_vehicles', [
            'project_uuid' => $this->proyectoA1,
            'third_party_uuid' => $terceroB,
            'vehicle_uuid' => $vehiculo4,
            'is_active' => 1,
        ]);

        $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos(
                $this->proyectoA1,
                [$this->vehiculos[1], $vehiculo4],
            ))
            ->assertStatus(422);

        // Transacción única: ni la primera hoja quedó persistida.
        $this->assertSame(0, DB::table('service_delivery_control_sheet')->count());
        $this->assertSame(0, DB::table('service_internal_controls')->count());
    }

    /**
     * Retiro del coche del proyecto (SPEC-009): al desactivarse la asignación,
     * las hojas ya creadas de ese coche se marcan con `retirada_de_proyecto`,
     * salen del listado (paginado y /list) y se conservan como histórico.
     */
    public function test_al_retirar_el_coche_del_proyecto_sus_hojas_salen_del_listado(): void
    {
        $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos($this->proyectoA1))
            ->assertCreated();

        // El formulario del proyecto devolvió solo los coches 1 y 3: la
        // asignación del coche 2 queda inactiva y sus hojas se retiran.
        $this->retirarCoche(2);

        // Retiradas: el padre y los dos hijos del coche 2.
        $this->assertSame(1, $this->hojasDe(2)->whereNull('parent_uuid')->where('retirada_de_proyecto', 1)->count());
        $this->assertSame(2, $this->hojasDe(2)->whereNotNull('parent_uuid')->where('retirada_de_proyecto', 1)->count());
        // Los coches que siguen en el proyecto no tienen ninguna retirada.
        $this->assertSame(0, $this->hojasDe(1)->where('retirada_de_proyecto', 1)->count());
        $this->assertSame(0, $this->hojasDe(3)->where('retirada_de_proyecto', 1)->count());

        // El listado paginado ya no trae las hojas del coche retirado...
        $filas = $this->comoAdminA()
            ->getJson('/api/v1/control-sheets/service-delivery-control-sheets')
            ->assertOk()
            ->json('data.data');
        $this->assertSame(['MASA01', 'MASA03'], collect($filas)
            ->pluck('vehicle_license_plate')->unique()->sort()->values()->all());

        // ...ni el catálogo /list.
        $catalogo = $this->comoAdminA()
            ->getJson('/api/v1/control-sheets/service-delivery-control-sheets/list')
            ->assertOk()
            ->json('data');
        $this->assertSame(['MASA01', 'MASA03'], collect($catalogo)
            ->pluck('vehicle_license_plate')->unique()->sort()->values()->all());
    }

    /**
     * Reactivación del coche (SPEC-009): sus hojas desmarcan
     * `retirada_de_proyecto` y vuelven al listado respetando el `is_active`
     * de cierre que ya tuviesen.
     */
    public function test_al_reactivar_el_coche_sus_hojas_vuelven_respectando_el_cierre(): void
    {
        $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos($this->proyectoA1))
            ->assertCreated();

        // Un día del coche 2 ya se cerró (congelamiento, SPEC-002 §7.2).
        DB::table('service_delivery_control_sheet')
            ->whereNotNull('parent_uuid')
            ->whereIn('uuid', fn ($q) => $q->select('service_delivery_control_sheet_uuid')
                ->from('service_internal_controls')
                ->where('vehicle_uuid', $this->vehiculos[2]))
            ->limit(1)
            ->update(['is_active' => 0]);

        $this->retirarCoche(2);
        $this->assertSame(3, $this->hojasDe(2)->where('retirada_de_proyecto', 1)->count());

        // El formulario vuelve a incluir el coche 2 con asignación activa.
        $this->comoAdminA()
            ->putJson("/api/v1/projects/{$this->proyectoA1}", ['assignments' => [
                ['third_party_uuid' => $this->conductores[1], 'vehicle_uuid' => $this->vehiculos[1], 'is_active' => true],
                ['third_party_uuid' => $this->conductores[2], 'vehicle_uuid' => $this->vehiculos[2], 'is_active' => true],
                ['third_party_uuid' => $this->conductores[3], 'vehicle_uuid' => $this->vehiculos[3], 'is_active' => true],
            ]])
            ->assertOk();

        // Desmarcadas todas (padre e hijos) y el cierre del día intacto.
        $this->assertSame(3, $this->hojasDe(2)->where('retirada_de_proyecto', 0)->count());
        $this->assertSame(0, $this->hojasDe(2)->where('retirada_de_proyecto', 1)->count());
        $this->assertSame(1, $this->hojasDe(2)->whereNull('parent_uuid')->where('is_active', 1)->count());
        $this->assertSame(1, $this->hojasDe(2)->whereNotNull('parent_uuid')->where('is_active', 0)->count());

        // Reaparecen en el listado paginado.
        $filas = $this->comoAdminA()
            ->getJson('/api/v1/control-sheets/service-delivery-control-sheets')
            ->assertOk()
            ->json('data.data');
        $this->assertSame(['MASA01', 'MASA02', 'MASA03'], collect($filas)
            ->pluck('vehicle_license_plate')->unique()->sort()->values()->all());
    }

    /** Filas (padres e hijos) cuyo control interno pertenece al coche dado. */
    private function hojasDe(int $i)
    {
        return DB::table('service_delivery_control_sheet')
            ->whereIn('uuid', fn ($q) => $q->select('service_delivery_control_sheet_uuid')
                ->from('service_internal_controls')
                ->where('vehicle_uuid', $this->vehiculos[$i]));
    }

    /** Devuelve el PUT del formulario del proyecto sin el coche dado. */
    private function retirarCoche(int $i): void
    {
        $assignments = [];
        foreach ($this->conductores as $j => $conductor) {
            if ($j !== $i) {
                $assignments[] = [
                    'third_party_uuid' => $conductor,
                    'vehicle_uuid' => $this->vehiculos[$j],
                    'is_active' => true,
                ];
            }
        }

        $this->comoAdminA()
            ->putJson("/api/v1/projects/{$this->proyectoA1}", ['assignments' => $assignments])
            ->assertOk();
    }

    /**
     * El proyecto más grande local (14 coches) × 30 días: 14 hojas, 420
     * planillas diarias, 434 controles internos. La primera medición local dio
     * 3,33 s (2026-10-21): el tope deja margen ×10 ajustado al medido; el
     * presupuesto real de fallo es el max_execution_time del worker.
     */
    #[Group('perf')]
    public function test_presupuesto_de_tiempo_del_bulk_de_14_coches_por_30_dias(): void
    {        // Amplía el proyecto de 3 a 14 asignaciones activas.
        for ($i = 4; $i <= 14; $i++) {
            $conductor = $this->crearTercero($this->empresaA, '9100002'.$i, 'T'.$i, $this->sedeA1);
            $vehiculo = $this->crearVehiculo($this->empresaA, $conductor, 'MASA'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $this->sedeA1);
            $this->insertar('project_driver_vehicles', [
                'project_uuid' => $this->proyectoA1,
                'third_party_uuid' => $conductor,
                'vehicle_uuid' => $vehiculo,
                'is_active' => 1,
            ]);
            $this->vehiculos[$i] = $vehiculo;
        }
        $coches = array_values($this->vehiculos);

        $inicio = microtime(true);
        $respuesta = $this->comoAdminA()
            ->postJson('/api/v1/control-sheets/service-delivery-control-sheets/bulk', $this->datos(
                $this->proyectoA1,
                $coches,
                '2026-11-08', // 30 días (10-10 → 11-08)
            ));
        $segundos = microtime(true) - $inicio;

        $respuesta->assertCreated()
            ->assertJsonPath('data.created', 14)
            ->assertJsonPath('data.daily_sheets', 420);

        $this->assertSame(434, DB::table('service_internal_controls')->count());

        if (getenv('CI')) {
            $this->addWarning('Medición del bulk deshabilitada en CI.');

            return; // medición solo en local
        }

        fwrite(STDERR, 'bulk 14x30: '.round($segundos, 2).' s');
        $this->assertLessThan(33.0, $segundos, 'El bulk de 14 coches × 30 días superó el presupuesto de tiempo del worker.');
    }
}
