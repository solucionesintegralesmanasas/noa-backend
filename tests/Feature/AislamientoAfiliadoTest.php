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
 * SPEC-005: un afiliado solo ve y modifica lo suyo, aunque comparta empresa con otros afiliados.
 *
 * Escenario: empresa E con dos afiliados (TURISVAL, NIT 901301544, proyecto en Cali, y otro afiliado B)
 * y una empresa F con su propio afiliado. Las pruebas corren con `AUTHZ_ENFORCE=false` (valor de
 * producción hoy): el aislamiento no puede depender del middleware `authz`.
 */
class AislamientoAfiliadoTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/fleet-management';

    private string $empresaE;

    private string $empresaF;

    private string $terceroA;

    private string $terceroB;

    private string $terceroF;

    private User $afiliadoA;

    private User $afiliadoB;

    private User $afiliadoF;

    private User $admin;

    /** @var array<string, array<string, string>> recursos por afiliado: ['A'|'B'|'F' => ['vehiculo' => uuid, ...]] */
    private array $datos = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        foreach (['vehicles.index', 'vehicles.profile', 'vehicle_documents.index', 'operation_cards.index', 'affiliate.index', 'locations.view', 'locations.history', 'reports.vehicles.index', 'reports.vehicles.export-excel', 'reports.vehicles.export-pdf'] as $p) {
            Permission::findOrCreate($p, 'api');
        }

        $this->empresaE = $this->empresa('Transportes E');
        $this->empresaF = $this->empresa('Transportes F');

        $this->terceroA = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaE, 'person_type' => 'JURIDICA', 'document_number' => '901301544',
            'company_name' => 'TURISVAL S.A.S', 'is_affiliate' => 1, 'is_active' => 1,
        ]);
        $this->terceroB = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaE, 'person_type' => 'JURIDICA', 'document_number' => '900111222',
            'company_name' => 'OTRO AFILIADO S.A.S', 'is_affiliate' => 1, 'is_active' => 1,
        ]);
        $this->terceroF = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaF, 'person_type' => 'JURIDICA', 'document_number' => '800333444',
            'company_name' => 'AFILIADO DE F S.A.S', 'is_affiliate' => 1, 'is_active' => 1,
        ]);

        $this->afiliadoA = $this->usuario('AFILIADO', $this->empresaE, $this->terceroA);
        $this->afiliadoB = $this->usuario('AFILIADO', $this->empresaE, $this->terceroB);
        $this->afiliadoF = $this->usuario('AFILIADO', $this->empresaF, $this->terceroF);
        $this->admin = $this->usuario('ADMIN_EMPRESA', $this->empresaE, null);

        $this->datos['A'] = $this->sembrarAfiliado($this->empresaE, $this->terceroA, 'AAA');
        $this->datos['B'] = $this->sembrarAfiliado($this->empresaE, $this->terceroB, 'BBB');
        $this->datos['F'] = $this->sembrarAfiliado($this->empresaF, $this->terceroF, 'FFF');
    }

    // ── Escenario ────────────────────────────────────────────────────────────────

    private function empresa(string $nombre): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => $nombre, 'municipality_uuid' => $relleno, 'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno, 'web_page' => 'https://e.test',
            'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function usuario(string $rol, string $empresa, ?string $tercero): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba '.$rol, 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate($rol, 'api'));
        if ($rol === 'AFILIADO') {
            foreach (['vehicles.index', 'vehicles.profile', 'vehicle_documents.index', 'operation_cards.index', 'affiliate.index', 'locations.view', 'locations.history'] as $p) {
                $user->givePermissionTo($p);
            }
        } else {
            $user->givePermissionTo(Permission::all());
        }
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $empresa, 'third_party_uuid' => $tercero,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    /** Un vehículo con documento y tarjeta, más un conductor vinculado, de un afiliado. */
    private function sembrarAfiliado(string $empresa, string $tercero, string $prefijo): array
    {
        $marca = $this->insertar('brands', ['description' => 'Marca '.$prefijo]);
        $clase = $this->insertar('vehicle_class', ['description' => 'Clase '.$prefijo]);
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $empresa, 'vehicle_license_plate' => $prefijo.'123', 'is_active' => true,
            'type_of_service' => 'PUBLICO', 'line' => 'ORIGINAL', 'brand_uuid' => $marca,
            'vehicle_class_uuid' => $clase, 'third_party_uuid' => $tercero,
        ]);
        $documento = $this->insertar('vehicle_documents', [
            'company_uuid' => $empresa, 'vehicle_uuid' => $vehiculo, 'document_type' => 'SOAT',
            'expiry_date' => '2026-12-31', 'status' => 'VIGENTE',
        ]);
        $tarjeta = $this->insertar('operation_cards', [
            'company_uuid' => $empresa, 'vehicle_uuid' => $vehiculo, 'operating_card_number' => 'TC-'.$prefijo,
            'expiration_date' => '2026-12-31', 'status' => true,
        ]);
        $inspeccion = $this->insertar('vehicle_inspections', [
            'company_uuid' => $empresa, 'vehicle_uuid' => $vehiculo, 'inspection_date' => '2026-09-30', 'mileage' => 1000,
        ]);
        $conductor = $this->insertar('third_parties', [
            'company_uuid' => $empresa, 'first_name' => 'Conductor', 'last_name' => $prefijo, 'is_driver' => 1, 'is_active' => 1,
        ]);
        $licencia = $this->insertar('driver_licenses', [
            'company_uuid' => $empresa, 'third_party_uuid' => $conductor, 'status' => 'VIGENTE',
        ]);
        $vinculo = $this->insertar('owners_drivers', [
            'third_party_uuid' => $tercero, 'driver_license_uuid' => $licencia,
        ]);

        $this->insertar('driver_location_sessions', [
            'company_uuid' => $empresa, 'third_party_uuid' => $conductor, 'status' => 'active', 'started_at' => now(),
        ]);
        $this->insertar('driver_locations', [
            'company_uuid' => $empresa, 'third_party_uuid' => $conductor, 'vehicle_uuid' => $vehiculo,
            'latitude' => 3.4516, 'longitude' => -76.5320, 'recorded_at' => now(),
        ]);

        return compact('vehiculo', 'documento', 'tarjeta', 'inspeccion', 'conductor', 'licencia', 'vinculo');
    }

    private function como(User $usuario, string $empresa): self
    {
        Sanctum::actingAs($usuario, ['*']);
        $this->withHeaders(['X-Company-UUID' => $empresa]);

        return $this;
    }

    /** UUID de los registros de una respuesta de listado (paginada o no). */
    private function uuids($respuesta): array
    {
        $json = $respuesta->json();
        $filas = $json['data']['data'] ?? $json['data'] ?? [];

        return collect($filas)->pluck('uuid')->filter()->values()->all();
    }

    // ── Listados ─────────────────────────────────────────────────────────────────

    public function test_el_afiliado_solo_lista_sus_vehiculos(): void
    {
        $r = $this->como($this->afiliadoA, $this->empresaE)->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaE);

        $r->assertOk();
        $uuids = $this->uuids($r);
        $this->assertContains($this->datos['A']['vehiculo'], $uuids, 'Debe ver su propio vehículo');
        $this->assertNotContains($this->datos['B']['vehiculo'], $uuids, 'No debe ver el vehículo de otro afiliado');
    }

    public function test_quitar_o_cambiar_third_party_uuid_no_amplia_el_listado(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $sin = $this->uuids($this->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaE));
        $ajeno = $this->uuids($this->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaE.'&third_party_uuid='.$this->terceroB));

        $this->assertNotContains($this->datos['B']['vehiculo'], $sin, 'Sin third_party_uuid filtra el del usuario');
        $this->assertNotContains($this->datos['B']['vehiculo'], $ajeno, 'Con el third_party_uuid de otro afiliado no debe ver lo ajeno');
    }

    public function test_el_afiliado_solo_lista_sus_documentos_y_tarjetas(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $docs = $this->uuids($this->getJson(self::BASE.'/vehicle-documents?company_uuid='.$this->empresaE));
        $tarjetas = $this->uuids($this->getJson(self::BASE.'/operation-cards?company_uuid='.$this->empresaE));

        $this->assertContains($this->datos['A']['documento'], $docs);
        $this->assertNotContains($this->datos['B']['documento'], $docs, 'Documento de otro afiliado');
        $this->assertContains($this->datos['A']['tarjeta'], $tarjetas);
        $this->assertNotContains($this->datos['B']['tarjeta'], $tarjetas, 'Tarjeta de otro afiliado');
    }

    public function test_el_afiliado_solo_ve_a_si_mismo_y_a_sus_conductores_en_terceros(): void
    {
        $r = $this->como($this->afiliadoA, $this->empresaE)->getJson('/api/v1/third-parties?company_uuid='.$this->empresaE.'&per_page=100');

        $r->assertOk();
        $uuids = $this->uuids($r);
        $this->assertContains($this->terceroA, $uuids);
        $this->assertContains($this->datos['A']['conductor'], $uuids, 'Su conductor vinculado');
        $this->assertNotContains($this->terceroB, $uuids, 'Otro afiliado');
        $this->assertNotContains($this->datos['B']['conductor'], $uuids, 'Conductor de otro afiliado');
    }

    // ── Lectura, edición y borrado por UUID ──────────────────────────────────────

    public function test_el_afiliado_no_abre_el_vehiculo_de_otro_afiliado_por_uuid(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $this->getJson(self::BASE.'/vehicles/'.$this->datos['A']['vehiculo'])->assertOk();
        $this->assertContains(
            $this->getJson(self::BASE.'/vehicles/'.$this->datos['B']['vehiculo'])->status(),
            [403, 404],
            'GET del vehículo de otro afiliado debe responder 404 (o 403)'
        );
    }

    public function test_el_afiliado_no_abre_documento_ni_tarjeta_ajenos_por_uuid(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $this->assertContains($this->getJson(self::BASE.'/vehicle-documents/'.$this->datos['B']['documento'])->status(), [403, 404], 'Documento ajeno');
        $this->assertContains($this->getJson(self::BASE.'/operation-cards/'.$this->datos['B']['tarjeta'])->status(), [403, 404], 'Tarjeta ajena');
    }

    public function test_el_afiliado_no_edita_el_vehiculo_de_otro_afiliado(): void
    {
        $r = $this->como($this->afiliadoA, $this->empresaE)
            ->putJson(self::BASE.'/vehicles/'.$this->datos['B']['vehiculo'], ['line' => 'HACKEADO']);

        $this->assertContains($r->status(), [403, 404], 'PUT ajeno respondió '.$r->status());
        $this->assertSame('ORIGINAL', DB::table('vehicles')->where('uuid', $this->datos['B']['vehiculo'])->value('line'), 'La fila ajena cambió');
    }

    public function test_el_afiliado_no_elimina_recursos_de_otro_afiliado(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $v = $this->deleteJson(self::BASE.'/vehicles/'.$this->datos['B']['vehiculo']);
        $d = $this->deleteJson(self::BASE.'/vehicle-documents/'.$this->datos['B']['documento']);
        $t = $this->deleteJson(self::BASE.'/operation-cards/'.$this->datos['B']['tarjeta']);

        $this->assertContains($v->status(), [403, 404], 'DELETE vehículo ajeno respondió '.$v->status());
        $this->assertContains($d->status(), [403, 404], 'DELETE documento ajeno respondió '.$d->status());
        $this->assertContains($t->status(), [403, 404], 'DELETE tarjeta ajena respondió '.$t->status());
        $this->assertTrue(DB::table('vehicles')->where('uuid', $this->datos['B']['vehiculo'])->exists(), 'El vehículo ajeno se borró');
        $this->assertTrue(DB::table('vehicle_documents')->where('uuid', $this->datos['B']['documento'])->exists(), 'El documento ajeno se borró');
        $this->assertTrue(DB::table('operation_cards')->where('uuid', $this->datos['B']['tarjeta'])->exists(), 'La tarjeta ajena se borró');
    }

    public function test_un_afiliado_registrado_como_propietario_no_abre_inspecciones_ajenas(): void
    {
        // A figura en `owners` (copropietario de su propio vehículo): el filtro mal agrupado
        // hacía verdadero el EXISTS para todas las inspecciones de la empresa.
        $this->insertar('owners', ['vehicle_uuid' => $this->datos['A']['vehiculo'], 'third_party_uuid' => $this->terceroA]);
        $this->como($this->afiliadoA, $this->empresaE);

        $this->assertContains(
            $this->getJson(self::BASE.'/vehicle-inspections/'.$this->datos['B']['inspeccion'])->status(),
            [403, 404],
            'Inspección de otro afiliado visible para un copropietario'
        );
        $r = $this->putJson(self::BASE.'/vehicle-inspections/'.$this->datos['B']['inspeccion'], ['mileage' => 999999]);
        $this->assertContains($r->status(), [403, 404], 'PUT de inspección ajena respondió '.$r->status());
        $this->assertSame(1000, (int) DB::table('vehicle_inspections')->where('uuid', $this->datos['B']['inspeccion'])->value('mileage'));
        $this->assertContains(
            $this->getJson(self::BASE.'/vehicle-inspections/'.$this->datos['A']['inspeccion'])->status(),
            [200],
            'Debe seguir viendo su propia inspección'
        );
    }

    // ── Rastreo (mapa en vivo e historial) ───────────────────────────────────────

    public function test_el_afiliado_rastrea_solo_a_sus_conductores(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $r = $this->getJson('/api/v1/tracking/active-drivers');

        $r->assertOk();
        $conductores = collect($r->json('data'))->pluck('third_party_uuid')->all();
        $this->assertContains($this->datos['A']['conductor'], $conductores, 'Debe ver a su conductor en el mapa');
        $this->assertNotContains($this->datos['B']['conductor'], $conductores, 'No debe ver al conductor de otro afiliado');
    }

    public function test_el_afiliado_no_consulta_ubicacion_ni_historial_de_conductores_ajenos(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);
        $rango = '?start_date=2026-10-01&end_date=2026-10-05';

        $this->getJson('/api/v1/tracking/last-location/'.$this->datos['A']['conductor'])->assertOk();
        $this->assertSame(404, $this->getJson('/api/v1/tracking/last-location/'.$this->datos['B']['conductor'])->status(), 'Última ubicación ajena');
        $this->getJson('/api/v1/tracking/driver/'.$this->datos['A']['conductor'].'/history'.$rango)->assertOk();
        $this->assertSame(404, $this->getJson('/api/v1/tracking/driver/'.$this->datos['B']['conductor'].'/history'.$rango)->status(), 'Historial ajeno');
        $this->assertSame(404, $this->getJson('/api/v1/tracking/driver/'.$this->datos['B']['conductor'].'/stats')->status(), 'Estadísticas ajenas');
    }

    public function test_el_administrador_ve_en_el_mapa_a_los_conductores_de_todos_los_afiliados(): void
    {
        $r = $this->como($this->admin, $this->empresaE)->getJson('/api/v1/tracking/active-drivers');

        $conductores = collect($r->json('data'))->pluck('third_party_uuid')->all();
        $this->assertContains($this->datos['A']['conductor'], $conductores);
        $this->assertContains($this->datos['B']['conductor'], $conductores);
        $this->assertNotContains($this->datos['F']['conductor'], $conductores, 'Otra empresa');
    }

    public function test_el_afiliado_no_tiene_acceso_al_reporte_de_vehiculos(): void
    {
        $this->como($this->afiliadoA, $this->empresaE);

        $this->assertSame(403, $this->getJson('/api/v1/reports/vehicles')->status());
        $this->assertSame(403, $this->getJson('/api/v1/reports/vehicles/excel')->status());
        $this->assertSame(403, $this->getJson('/api/v1/reports/vehicles/pdf')->status());
        $this->assertNotSame(403, $this->como($this->admin, $this->empresaE)->getJson('/api/v1/reports/vehicles')->status(), 'El administrador sí accede');
    }

    // ── Sin tercero, otra empresa y controles ────────────────────────────────────

    public function test_un_afiliado_sin_tercero_recibe_listas_vacias(): void
    {
        $sinTercero = $this->usuario('AFILIADO', $this->empresaE, null);

        $r = $this->como($sinTercero, $this->empresaE)->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaE);

        $r->assertOk();
        $this->assertSame([], $this->uuids($r), 'Un afiliado sin third_party_uuid no debe ver la flota de la empresa');
    }

    public function test_el_afiliado_no_opera_en_otra_empresa(): void
    {
        $this->como($this->afiliadoA, $this->empresaF)
            ->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaF)
            ->assertForbidden();
    }

    public function test_el_afiliado_de_otra_empresa_no_ve_nada_de_la_empresa_e(): void
    {
        $this->como($this->afiliadoF, $this->empresaF);

        $uuids = $this->uuids($this->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaF));

        $this->assertContains($this->datos['F']['vehiculo'], $uuids);
        $this->assertNotContains($this->datos['A']['vehiculo'], $uuids);
        $this->assertNotContains($this->datos['B']['vehiculo'], $uuids);
        $this->assertContains(
            $this->getJson(self::BASE.'/vehicles/'.$this->datos['A']['vehiculo'])->status(),
            [403, 404],
            'GET por UUID entre empresas'
        );
    }

    public function test_el_administrador_de_la_empresa_sigue_viendo_a_todos_los_afiliados(): void
    {
        $uuids = $this->uuids($this->como($this->admin, $this->empresaE)->getJson(self::BASE.'/vehicles?company_uuid='.$this->empresaE));

        $this->assertContains($this->datos['A']['vehiculo'], $uuids);
        $this->assertContains($this->datos['B']['vehiculo'], $uuids);
        $this->assertNotContains($this->datos['F']['vehiculo'], $uuids, 'Otra empresa');
    }
}
