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
 * SPEC-007: permisos por ruta, empresa del contexto, código único y orden de pasos, vistos desde HTTP.
 */
class RadicacionHttpTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $padre;

    /** @var array<string, string> */
    private array $pasos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Radicación HTTP', 'is_active' => true]);
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
}
