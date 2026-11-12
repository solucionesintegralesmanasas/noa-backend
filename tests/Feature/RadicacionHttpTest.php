<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Procedure\RadicacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
 * SPEC-007: permisos por ruta, empresa del contexto, código único y orden de pasos, vistos desde HTTP.
 */
class RadicacionHttpTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $origen;

    private string $padre;

    /** @var array<string, string> */
    private array $pasos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Radicación HTTP', 'is_active' => true]);
        $this->origen = $this->insertar('companies', ['business_name' => 'Empresa Origen', 'is_active' => true]);
        $resolucion = $this->insertar('enabling_resolutions', ['company_uuid' => $this->empresa]);
        $this->insertar('conveyor_capacity', [
            'enabling_resolution_uuid' => $resolucion,
            'vehicle_type' => 'CAMION',
            'authorized_capacity' => 5,
            'current_capacity' => 5,
            'minimum_own_capacity' => 1,
            'status' => 1,
        ]);
        $this->padre = $this->insertar('procedures', [
            'company_uuid' => $this->empresa, 'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'INCLUSION_DE_POLIZAS', 'procedure_code' => 'EXP-1', 'status' => 'EN_PROCESO',
        ]);
        foreach (['INCLUSION_DE_POLIZAS', 'CARTA_DE_ACEPTACION', 'CAPACIDAD_TRANSPORTADORA', 'TARJETA_DE_OPERACION'] as $i => $tipo) {
            $this->pasos[$tipo] = $this->insertar('procedures', [
                'company_uuid' => $this->empresa, 'parent_procedure_uuid' => $this->padre,
                'link_type' => 'CAMBIO_DE_EMPRESA', 'procedure_type' => $tipo,
                'procedure_code' => 'EXP-1-'.substr($tipo, 0, 3), 'status' => $i === 0 ? 'EN_PROCESO' : 'RECIBIDO',
            ]);
        }
    }

    private function usuario(string $rolNombre, array $permisos): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $rol = Role::findOrCreate($rolNombre, 'api');
        foreach ($permisos as $p) {
            $rol->givePermissionTo(Permission::findOrCreate($p, 'api'));
        }
        $user->assignRole($rol);
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $this->empresa, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function admin(): User
    {
        return $this->usuario('ADMIN_EMPRESA', ['radicacion_to.index', 'radicacion_to.create', 'radicacion_to.update']);
    }

    public function test_un_afiliado_sin_permisos_recibe_403_en_todas_las_rutas(): void
    {
        Sanctum::actingAs($this->usuario('AFILIADO', []), ['*']);
        $base = '/api/v1/procedure/radicacion';

        $this->getJson("$base/expedientes")->assertForbidden();
        $this->getJson("$base/{$this->padre}/detalle")->assertForbidden();
        $this->getJson("$base/{$this->padre}/ruta")->assertForbidden();
        $this->getJson("$base/{$this->padre}/validar")->assertForbidden();
        $this->getJson("$base/{$this->padre}/documento/carta")->assertForbidden();
        $this->postJson("$base/expediente", [])->assertForbidden();
        $this->postJson("$base/enlace-firma", [])->assertForbidden();
        $this->postJson("$base/{$this->pasos['INCLUSION_DE_POLIZAS']}/avanzar")->assertForbidden();
        $this->postJson("$base/{$this->padre}/txt", [])->assertForbidden();
    }

    public function test_solo_lectura_ve_pero_no_escribe(): void
    {
        Sanctum::actingAs($this->usuario('LECTOR_RADICACION', ['radicacion_to.index']), ['*']);
        $base = '/api/v1/procedure/radicacion';

        $this->getJson("$base/expedientes")->assertOk();
        $this->postJson("$base/{$this->pasos['INCLUSION_DE_POLIZAS']}/avanzar")->assertForbidden();
        $this->postJson("$base/expediente", [])->assertForbidden();
    }

    public function test_no_se_puede_saltar_un_paso_por_http(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $this->postJson("/api/v1/procedure/radicacion/{$this->pasos['TARJETA_DE_OPERACION']}/avanzar")
            ->assertStatus(422);

        $this->assertSame('RECIBIDO', DB::table('procedures')->where('uuid', $this->pasos['TARJETA_DE_OPERACION'])->value('status'));
    }

    public function test_un_paso_completado_no_se_repite_por_http(): void
    {
        DB::table('procedures')->where('uuid', $this->pasos['INCLUSION_DE_POLIZAS'])->update(['status' => 'COMPLETADO']);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->postJson("/api/v1/procedure/radicacion/{$this->pasos['INCLUSION_DE_POLIZAS']}/avanzar")
            ->assertStatus(422);
    }

    public function test_el_enlace_de_firma_rechaza_una_empresa_ajena(): void
    {
        $ajena = $this->insertar('companies', ['business_name' => 'Otra', 'is_active' => true]);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/enlace-firma', ['company_uuid' => $ajena])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'company_uuid', 'code' => 'INVALID_FIELD_VALUE']);
    }

    public function test_el_codigo_de_tramite_no_se_repite_en_la_empresa(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', ['company_uuid' => $this->empresa, 'procedure_code' => 'EXP-1'])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'procedure_code', 'code' => 'INVALID_FIELD_VALUE']);
    }

    public function test_el_enlace_de_firma_rechaza_un_contrato_de_otra_empresa(): void
    {
        $ajena = $this->insertar('companies', ['business_name' => 'Ajena', 'is_active' => true]);
        $procAjeno = $this->insertar('procedures', ['company_uuid' => $ajena]);
        $contratoAjeno = $this->insertar('service_provision_contracts', [
            'company_uuid' => $ajena, 'procedure_uuid' => $procAjeno, 'contract_number' => 'SPC-AJENO',
            'issue_date' => '2026-01-01', 'start_date' => '2026-02-01', 'end_date' => '2026-12-31',
            'duration' => 300, 'status' => 'PENDIENTE_FIRMA',
        ]);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/enlace-firma', [
                'company_uuid' => $this->empresa, 'contract_origin' => 'PRESTACION', 'contract_uuid' => $contratoAjeno,
                'signer_role' => 'REP_LEGAL', 'signer_name' => 'Firmante', 'signer_document' => '123',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'contract_uuid']);

        $this->assertSame(0, DB::table('contract_signatures')->where('contract_uuid', $contratoAjeno)->count());
    }

    public function test_el_expediente_sin_empresa_usa_la_del_contexto(): void
    {
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        $ciudad = $this->insertar('cities', []);
        $director = $this->insertar('territorial_directors', []);
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', [
                'link_type' => 'CAMBIO_DE_EMPRESA',
                'origin_company_uuid' => $this->origen,
                'vehicle_uuid' => $vehiculo,
                'procedure_code' => 'EXP-SIN-EMPRESA',
                'date_of_creation' => '2026-10-07',
                'city_uuid' => $ciudad,
                'territorial_director_uuid' => $director,
            ])
            ->assertCreated();

        $uuid = $respuesta->json('data.expediente.uuid');
        $this->assertSame($this->empresa, DB::table('procedures')->where('uuid', $uuid)->value('company_uuid'));
    }

    public function test_el_expediente_se_crea_sin_direccion_territorial(): void
    {
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        $ciudad = $this->insertar('cities', []);
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', [
                'link_type' => 'CAMBIO_DE_EMPRESA',
                'origin_company_uuid' => $this->origen,
                'vehicle_uuid' => $vehiculo,
                'procedure_code' => 'EXP-SIN-DIR',
                'date_of_creation' => '2026-10-16',
                'city_uuid' => $ciudad,
            ])
            ->assertCreated();

        $uuid = $respuesta->json('data.expediente.uuid');
        $this->assertNull(DB::table('procedures')->where('uuid', $uuid)->value('territorial_director_uuid'));
    }

    public function test_el_expediente_guarda_la_direccion_territorial_en_texto(): void
    {
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        $ciudad = $this->insertar('cities', []);
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', [
                'link_type' => 'CAMBIO_DE_EMPRESA',
                'origin_company_uuid' => $this->origen,
                'vehicle_uuid' => $vehiculo,
                'procedure_code' => 'EXP-TERRITORIAL',
                'date_of_creation' => '2026-10-16',
                'city_uuid' => $ciudad,
                'territorial_director_name' => 'Ana María Pérez',
                'territorial_name' => 'Cesar',
            ])
            ->assertCreated();

        $uuid = $respuesta->json('data.expediente.uuid');
        $fila = DB::table('procedures')->where('uuid', $uuid)->first();
        $this->assertSame('Ana María Pérez', $fila->territorial_director_name);
        $this->assertSame('Cesar', $fila->territorial_name);
    }

    public function test_el_codigo_de_tramite_se_genera_si_no_viene(): void
    {
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        $ciudad = $this->insertar('cities', []);
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', [
                'link_type' => 'CAMBIO_DE_EMPRESA',
                'origin_company_uuid' => $this->origen,
                'vehicle_uuid' => $vehiculo,
                'date_of_creation' => '2026-10-16',
                'city_uuid' => $ciudad,
            ])
            ->assertCreated();

        $codigo = DB::table('procedures')->where('uuid', $respuesta->json('data.expediente.uuid'))->value('procedure_code');
        $this->assertMatchesRegularExpression('/^\d{14}$/', (string) $codigo);
    }

    public function test_el_siguiente_codigo_tiene_catorce_digitos(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson('/api/v1/procedure/radicacion/next-code')
            ->assertOk();

        $this->assertMatchesRegularExpression('/^\d{14}$/', (string) $respuesta->json('data.procedure_code'));
    }

    public function test_el_siguiente_codigo_rechaza_la_empresa_de_otra_cuenta(): void
    {
        $ajena = $this->insertar('companies', ['business_name' => 'Ajena', 'is_active' => true]);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson('/api/v1/procedure/radicacion/next-code?company_uuid='.$ajena)
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'company_uuid']);
    }

    public function test_el_codigo_de_tramite_falla_si_no_hay_ninguno_libre(): void
    {
        // Falla cerrado: nunca devuelve un código que ya existe en la empresa.
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $inicio = now();
        for ($i = 0; $i <= RadicacionService::INTENTOS_CODIGO; $i++) {
            $this->insertar('procedures', [
                'company_uuid' => $this->empresa,
                'procedure_code' => $inicio->copy()->addSeconds($i)->format('YmdHis'),
            ]);
        }
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson('/api/v1/procedure/radicacion/next-code')
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'procedure_code']);
    }

    public function test_el_documento_devuelve_pdf(): void
    {
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        DB::table('procedures')->where('uuid', $this->padre)->update(['vehicle_uuid' => $vehiculo]);
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson("/api/v1/procedure/radicacion/{$this->padre}/documento/CARTA_ACEPTACION");

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type') ?? '');
    }

    public function test_el_documento_con_clave_invalida_devuelve_404_json(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->getJson("/api/v1/procedure/radicacion/{$this->padre}/documento/CLAVE_QUE_NO_EXISTE");

        $respuesta->assertNotFound();
        $respuesta->assertJson(['success' => false]);
    }

    public function test_no_se_crea_un_expediente_sin_cupo_disponible(): void
    {
        DB::table('conveyor_capacity')->where('enabling_resolution_uuid', DB::table('enabling_resolutions')
            ->where('company_uuid', $this->empresa)->value('uuid'))
            ->update(['current_capacity' => 0]);
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        $ciudad = $this->insertar('cities', []);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', [
                'link_type' => 'CAMBIO_DE_EMPRESA',
                'origin_company_uuid' => $this->origen,
                'vehicle_uuid' => $vehiculo,
                'date_of_creation' => '2026-11-05',
                'city_uuid' => $ciudad,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'vehicle_uuid']);

        $this->assertSame(0, DB::table('procedures')->where('date_of_creation', '2026-11-05')
            ->where('company_uuid', $this->empresa)->whereNull('parent_procedure_uuid')->count());
    }

    public function test_la_empresa_origen_no_puede_ser_la_receptora(): void
    {
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        $ciudad = $this->insertar('cities', []);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson('/api/v1/procedure/radicacion/expediente', [
                'link_type' => 'CAMBIO_DE_EMPRESA',
                'origin_company_uuid' => $this->empresa,
                'vehicle_uuid' => $vehiculo,
                'date_of_creation' => '2026-11-05',
                'city_uuid' => $ciudad,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'origin_company_uuid']);
    }

    public function test_el_paso_guarda_los_datos_de_renovacion_y_paz_y_salvo(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->putJson("/api/v1/procedure/radicacion/{$this->pasos['INCLUSION_DE_POLIZAS']}", [
                'simit_clear' => true,
                'soat_valid' => true,
                'rtm_valid' => false,
                'old_card_number' => 'TO-123',
                'radicado_number' => 'RAD-9',
                'payment_status' => 'PAGADO',
                'paz_y_salvo' => 'PYS-2026-001',
                'subject' => 'Cambio de empresa',
            ])
            ->assertOk();

        $paso = DB::table('procedures')->where('uuid', $this->pasos['INCLUSION_DE_POLIZAS'])->first();
        $this->assertSame('TO-123', $paso->old_card_number);
        $this->assertSame('PAGADO', $paso->payment_status);

        // El asunto y el paz y salvo describen al expediente y se replican en todas las filas.
        foreach ([$this->padre, $this->pasos['TARJETA_DE_OPERACION']] as $fila) {
            $registro = DB::table('procedures')->where('uuid', $fila)->first();
            $this->assertSame('PYS-2026-001', $registro->paz_y_salvo);
            $this->assertSame('Cambio de empresa', $registro->subject);
        }
    }

    public function test_el_expediente_replica_asunto_y_paz_y_salvo_a_sus_pasos(): void
    {
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->putJson("/api/v1/procedure/radicacion/{$this->padre}", [
                'subject' => 'Expediente actualizado',
                'paz_y_salvo' => 'PYS-2026-002',
            ])
            ->assertOk();

        foreach (array_merge([$this->padre], array_values($this->pasos)) as $fila) {
            $registro = DB::table('procedures')->where('uuid', $fila)->first();
            $this->assertSame('Expediente actualizado', $registro->subject);
            $this->assertSame('PYS-2026-002', $registro->paz_y_salvo);
        }
    }

    public function test_un_paso_completado_no_admite_cambios_de_datos(): void
    {
        DB::table('procedures')->where('uuid', $this->pasos['INCLUSION_DE_POLIZAS'])->update(['status' => 'COMPLETADO']);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->putJson("/api/v1/procedure/radicacion/{$this->pasos['INCLUSION_DE_POLIZAS']}", [
                'paz_y_salvo' => 'PYS-TARDE',
            ])
            ->assertStatus(422);

        $this->assertNull(DB::table('procedures')->where('uuid', $this->padre)->value('paz_y_salvo'));
    }

    public function test_cerrar_la_capacidad_libera_el_cupo_en_la_empresa_de_origen(): void
    {
        $resolucionOrigen = $this->insertar('enabling_resolutions', ['company_uuid' => $this->origen]);
        $capacidadOrigen = $this->insertar('conveyor_capacity', [
            'enabling_resolution_uuid' => $resolucionOrigen,
            'vehicle_type' => 'CAMION',
            'authorized_capacity' => 5,
            'current_capacity' => 3,
            'minimum_own_capacity' => 1,
            'status' => 1,
        ]);
        $vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
        DB::table('procedures')->where('uuid', $this->padre)->update([
            'vehicle_uuid' => $vehiculo, 'origin_company_uuid' => $this->origen,
        ]);
        DB::table('procedures')->where('uuid', $this->pasos['INCLUSION_DE_POLIZAS'])->update(['status' => 'COMPLETADO']);
        DB::table('procedures')->where('uuid', $this->pasos['CARTA_DE_ACEPTACION'])->update(['status' => 'COMPLETADO']);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$this->pasos['CAPACIDAD_TRANSPORTADORA']}/avanzar")
            ->assertOk();

        $this->assertSame(4, (int) DB::table('conveyor_capacity')
            ->where('enabling_resolution_uuid', DB::table('enabling_resolutions')
                ->where('company_uuid', $this->empresa)->value('uuid'))
            ->value('current_capacity'));
        $this->assertSame(4, (int) DB::table('conveyor_capacity')->where('uuid', $capacidadOrigen)->value('current_capacity'));

        $this->assertSame(1, DB::table('capacity_movements')
            ->where('procedure_uuid', $this->pasos['CAPACIDAD_TRANSPORTADORA'])
            ->where('movement_type', 'DESCUENTO_INGRESO')->count());
        $this->assertSame(1, DB::table('capacity_movements')
            ->where('procedure_uuid', $this->pasos['CAPACIDAD_TRANSPORTADORA'])
            ->where('movement_type', 'LIBERACION_SALIDA')
            ->where('company_uuid', $this->origen)->count());
    }

    public function test_el_indice_unico_de_base_de_datos_impide_duplicados_de_codigo_en_la_misma_empresa(): void
    {
        $otraEmpresa = $this->insertar('companies', ['business_name' => 'Segunda Empresa', 'is_active' => true]);

        // Primera inserción en la empresa
        $this->insertar('procedures', [
            'company_uuid' => $this->empresa,
            'procedure_code' => 'EXP-DB-UNIQUE',
            'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'INCLUSION_DE_POLIZAS',
        ]);

        // Inserción en OTRA empresa con el mismo código es permitida
        $segunda = $this->insertar('procedures', [
            'company_uuid' => $otraEmpresa,
            'procedure_code' => 'EXP-DB-UNIQUE',
            'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'INCLUSION_DE_POLIZAS',
        ]);
        $this->assertNotNull($segunda);

        // Inserción duplicada en la misma empresa es rechazada a nivel de BD
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->insertar('procedures', [
            'company_uuid' => $this->empresa,
            'procedure_code' => 'EXP-DB-UNIQUE',
            'link_type' => 'CAMBIO_DE_EMPRESA',
            'procedure_type' => 'INCLUSION_DE_POLIZAS',
        ]);
    }
}
