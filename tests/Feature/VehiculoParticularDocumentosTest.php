<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VehicleExpiryDigestMail;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Notifications\EmailLogService;
use App\Services\Notifications\NotificationsService;
use App\Services\Reports\VehicleReportService;
use App\Utils\OwnCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SPEC-003: un vehículo PARTICULAR no tiene pólizas RCC/RCE ni tarjeta de operación (solo SOAT y RTM).
 * Se rechaza al registrar y el resto de flujos (alertas, correo, reporte) lo ignoran.
 */
class VehiculoParticularDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private const EMPRESA = 'TRANSPORTES ESPECIALES SIN BARRERA S.A.S';

    private const RUTA_DOCUMENTOS = '/api/v1/fleet-management/vehicle-documents';

    private const RUTA_TARJETAS = '/api/v1/fleet-management/operation-cards';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        OwnCompany::limpiarCache();
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OwnCompany::limpiarCache();
        parent::tearDown();
    }

    /** Inserta una fila rellenando las columnas obligatorias sin valor por defecto. */
    private function insertar(string $tabla, array $datos): string
    {
        $uuid = $datos['uuid'] ?? (string) Str::uuid();
        $datos['uuid'] = $uuid;

        $columnas = DB::select(
            'select column_name n, data_type t, column_type ct from information_schema.columns
             where table_schema = database() and table_name = ? and is_nullable = "NO"
             and column_default is null and extra not like "%auto_increment%"',
            [$tabla]
        );
        foreach ($columnas as $c) {
            if (array_key_exists($c->n, $datos)) {
                continue;
            }
            $datos[$c->n] = match (true) {
                in_array($c->t, ['char', 'varchar'], true) => $c->n === 'email' ? Str::random(6).'@x.test' : (str_ends_with($c->n, 'uuid') ? (string) Str::uuid() : 'x'),
                in_array($c->t, ['text', 'longtext', 'mediumtext'], true) => 'x',
                $c->t === 'enum' => explode("','", trim(substr($c->ct, 5, -1), "'"))[0],
                $c->t === 'date' => '2026-09-30',
                in_array($c->t, ['datetime', 'timestamp'], true) => '2026-09-30 10:00:00',
                $c->t === 'json' => '[]',
                default => 0,
            };
        }

        Schema::disableForeignKeyConstraints();
        DB::table($tabla)->insert($datos + ['created_at' => now(), 'updated_at' => now()]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function empresa(): string
    {
        $empresa = $this->insertar('companies', ['business_name' => self::EMPRESA, 'is_active' => true]);
        $this->insertar('system_configuration', [
            'company_uuid' => $empresa,
            'activate_notifications' => true,
            'notify_by_email' => true,
            'notification_email' => 'transespecialessinbarreras@gmail.com',
        ]);

        return $empresa;
    }

    private function vehiculo(string $empresa, string $placa, string $servicio, array $extra = []): string
    {
        return $this->insertar('vehicles', array_merge([
            'company_uuid' => $empresa,
            'vehicle_license_plate' => $placa,
            'type_of_service' => $servicio,
            'is_active' => true,
        ], $extra));
    }

    private function documento(string $empresa, string $vehiculo, string $tipo, string $vence, string $estado = 'VIGENTE'): string
    {
        return $this->insertar('vehicle_documents', [
            'vehicle_uuid' => $vehiculo, 'company_uuid' => $empresa,
            'document_type' => $tipo, 'expiry_date' => $vence, 'status' => $estado,
        ]);
    }

    private function tarjeta(string $empresa, string $vehiculo, string $afiliada = self::EMPRESA, string $vence = '2027-12-31'): string
    {
        return $this->insertar('operation_cards', [
            'vehicle_uuid' => $vehiculo, 'company_uuid' => $empresa,
            'operating_card_number' => 'TO-'.Str::random(4), 'affiliated_company' => $afiliada,
            'expiration_date' => $vence, 'status' => 1,
        ]);
    }

    private function superadmin(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Super', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('SUPERADMIN', 'api'));

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function cuerpoDocumento(string $empresa, string $vehiculo, string $tipo): array
    {
        return [
            'company_uuid' => $empresa, 'vehicle_uuid' => $vehiculo, 'document_type' => $tipo,
            'policy_number' => 'POL-1', 'issue_date' => '2026-01-01', 'expiry_date' => '2027-01-01',
            'issuing_entity' => 'Aseguradora', 'status' => 'VIGENTE',
        ];
    }

    /** @return array<string, mixed> */
    private function cuerpoTarjeta(string $empresa, string $vehiculo): array
    {
        return [
            'company_uuid' => $empresa, 'vehicle_uuid' => $vehiculo, 'affiliated_company' => self::EMPRESA,
            'service_type' => 'ESPECIAL', 'transport_mode' => 'TERRESTRE', 'issue_date' => '2026-01-01',
            'expiration_date' => '2027-01-01', 'operating_card_number' => 'TO-1',
        ];
    }

    // ------------------------------------------------------------------ regla de dominio

    public function test_la_regla_de_dominio_distingue_particulares(): void
    {
        $particular = new Vehicle(['type_of_service' => 'PARTICULAR']);
        $publico = new Vehicle(['type_of_service' => 'PUBLICO']);

        $this->assertTrue($particular->esParticular());
        $this->assertFalse($particular->requiereTarjetaOperacion());
        $this->assertFalse($particular->admiteTipoDocumento('RCC'));
        $this->assertFalse($particular->admiteTipoDocumento('rce'));
        $this->assertTrue($particular->admiteTipoDocumento('SOAT'));
        $this->assertTrue($particular->admiteTipoDocumento('RTM'));

        $this->assertTrue($publico->requiereTarjetaOperacion());
        $this->assertTrue($publico->admiteTipoDocumento('RCC'));
    }

    // ------------------------------------------------------------------ rechazo al registrar (API)

    public function test_no_se_puede_registrar_una_poliza_rcc_o_rce_a_un_particular(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        Sanctum::actingAs($this->superadmin(), ['*']);

        foreach (['RCC', 'RCE'] as $tipo) {
            $this->postJson(self::RUTA_DOCUMENTOS, $this->cuerpoDocumento($empresa, $vehiculo, $tipo), ['X-Company-UUID' => $empresa])
                ->assertStatus(422)
                ->assertJsonPath('error.details.0.field', 'document_type')
                ->assertJsonPath('error.details.0.messages.0', 'Un vehículo particular no tiene pólizas RCC/RCE: solo registra SOAT y tecnomecánica.');
        }
        $this->assertSame(0, DB::table('vehicle_documents')->where('vehicle_uuid', $vehiculo)->count());
    }

    public function test_un_particular_si_registra_soat_y_rtm(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        Sanctum::actingAs($this->superadmin(), ['*']);

        foreach (['SOAT', 'RTM'] as $tipo) {
            $this->postJson(self::RUTA_DOCUMENTOS, $this->cuerpoDocumento($empresa, $vehiculo, $tipo), ['X-Company-UUID' => $empresa])
                ->assertStatus(201);
        }
    }

    public function test_un_publico_sigue_registrando_polizas(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PUB111', 'PUBLICO');
        Sanctum::actingAs($this->superadmin(), ['*']);

        $this->postJson(self::RUTA_DOCUMENTOS, $this->cuerpoDocumento($empresa, $vehiculo, 'RCC'), ['X-Company-UUID' => $empresa])->assertStatus(201);
    }

    public function test_no_se_puede_registrar_tarjeta_de_operacion_a_un_particular(): void
    {
        $empresa = $this->empresa();
        $particular = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $publico = $this->vehiculo($empresa, 'PUB111', 'PUBLICO');
        Sanctum::actingAs($this->superadmin(), ['*']);

        $this->postJson(self::RUTA_TARJETAS, $this->cuerpoTarjeta($empresa, $particular), ['X-Company-UUID' => $empresa])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'vehicle_uuid')
            ->assertJsonPath('error.details.0.messages.0', 'Un vehículo particular no tiene tarjeta de operación.');
        $this->assertSame(0, DB::table('operation_cards')->where('vehicle_uuid', $particular)->count());

        $this->postJson(self::RUTA_TARJETAS, $this->cuerpoTarjeta($empresa, $publico), ['X-Company-UUID' => $empresa])->assertStatus(201);
    }

    public function test_no_se_puede_mover_una_poliza_o_tarjeta_existente_a_un_particular(): void
    {
        $empresa = $this->empresa();
        $particular = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $publico = $this->vehiculo($empresa, 'PUB111', 'PUBLICO');
        $poliza = $this->documento($empresa, $publico, 'RCC', '2027-01-01');
        $tarjeta = $this->tarjeta($empresa, $publico);
        Sanctum::actingAs($this->superadmin(), ['*']);
        $cab = ['X-Company-UUID' => $empresa];

        $this->putJson(self::RUTA_DOCUMENTOS.'/'.$poliza, ['vehicle_uuid' => $particular], $cab)->assertStatus(422);
        $this->putJson(self::RUTA_TARJETAS.'/'.$tarjeta, ['vehicle_uuid' => $particular], $cab)->assertStatus(422);
    }

    public function test_el_historial_de_un_vehiculo_que_pasa_a_particular_se_conserva_y_se_puede_editar(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $poliza = $this->documento($empresa, $vehiculo, 'RCC', '2027-01-01'); // historial anterior al cambio de tipo
        Sanctum::actingAs($this->superadmin(), ['*']);

        $this->putJson(self::RUTA_DOCUMENTOS.'/'.$poliza, ['policy_number' => 'CORREGIDA'], ['X-Company-UUID' => $empresa])
            ->assertSuccessful();
        $this->assertSame('CORREGIDA', DB::table('vehicle_documents')->where('uuid', $poliza)->value('policy_number'));
    }

    // ------------------------------------------------------------------ notificaciones

    public function test_un_particular_no_genera_alerta_de_tarjeta_faltante_pero_un_publico_si(): void
    {
        $empresa = $this->empresa();
        $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $this->vehiculo($empresa, 'PUB111', 'PUBLICO');

        $faltantes = collect(app(NotificationsService::class)->notificationsForOperationCards($empresa)['missing'])
            ->pluck('vehicle_license_plate')->all();

        $this->assertSame(['PUB111'], $faltantes);
    }

    public function test_un_particular_con_tarjeta_historica_tampoco_alerta_su_vencimiento(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $this->tarjeta($empresa, $vehiculo, self::EMPRESA, '2026-01-01'); // vencida

        $r = app(NotificationsService::class)->notificationsForOperationCards($empresa);

        $this->assertSame([], $r['expired']);
        $this->assertSame([], $r['missing']);
    }

    public function test_un_particular_solo_debe_tener_soat_y_rtm(): void
    {
        $empresa = $this->empresa();
        // La RTM solo se exige a partir de los 2 años de matriculado.
        $this->vehiculo($empresa, 'PAR111', 'PARTICULAR', ['registration_date' => '2020-01-01']);
        $this->vehiculo($empresa, 'PUB111', 'PUBLICO', ['registration_date' => '2020-01-01']);

        $faltantes = collect(app(NotificationsService::class)->notificationsForVehicleDocuments($empresa)['missing']);

        $this->assertEqualsCanonicalizing(['SOAT', 'RTM'], $faltantes->where('vehicle_license_plate', 'PAR111')->pluck('document_type')->all());
        $this->assertEqualsCanonicalizing(['SOAT', 'RTM', 'RCC', 'RCE'], $faltantes->where('vehicle_license_plate', 'PUB111')->pluck('document_type')->all());
    }

    // ------------------------------------------------------------------ correo de vencimientos

    private function documentosEnCorreo(): array
    {
        return Mail::sent(VehicleExpiryDigestMail::class)
            ->flatMap(fn (VehicleExpiryDigestMail $m) => $m->items)
            ->map(fn (array $i) => $i['placa'].'|'.$i['documento'])
            ->sort()->values()->all();
    }

    public function test_el_correo_incluye_soat_y_rtm_de_un_particular_sin_polizas_ni_tarjeta(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $this->documento($empresa, $vehiculo, 'SOAT', '2026-09-25');   // vencido
        $this->documento($empresa, $vehiculo, 'RTM', '2026-10-02');    // por vencer
        $this->documento($empresa, $vehiculo, 'RCC', '2026-09-25');    // histórico: no aplica
        $this->documento($empresa, $vehiculo, 'RCE', '2026-09-25');    // histórico: no aplica
        $this->tarjeta($empresa, $vehiculo, 'OTRA EMPRESA', '2026-09-25'); // histórica: no aplica

        app(EmailLogService::class)->notifyExpiringDocuments();

        $this->assertSame(['PAR111|RTM', 'PAR111|SOAT'], $this->documentosEnCorreo());
        Mail::assertSent(VehicleExpiryDigestMail::class, fn (VehicleExpiryDigestMail $m) => $m->hasTo('transespecialessinbarreras@gmail.com'));
    }

    public function test_el_correo_de_los_publicos_no_cambia(): void
    {
        $empresa = $this->empresa();
        $propio = $this->vehiculo($empresa, 'PUB111', 'PUBLICO');
        $this->tarjeta($empresa, $propio);
        $this->documento($empresa, $propio, 'RCC', '2026-09-25');
        $ajeno = $this->vehiculo($empresa, 'AFI222', 'PUBLICO');
        $this->tarjeta($empresa, $ajeno, 'COOPERATIVA AFILIADA S.A.');
        $this->documento($empresa, $ajeno, 'RCC', '2026-09-25');

        app(EmailLogService::class)->notifyExpiringDocuments();

        $this->assertSame(['PUB111|RCC'], $this->documentosEnCorreo());
    }

    // ------------------------------------------------------------------ reporte de vehículos

    public function test_el_reporte_marca_los_particulares_y_oculta_polizas_y_tarjeta(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'PAR111', 'PARTICULAR');
        $this->documento($empresa, $vehiculo, 'SOAT', '2027-01-01');
        $this->documento($empresa, $vehiculo, 'RCC', '2027-01-01');
        $this->tarjeta($empresa, $vehiculo);
        $publico = $this->vehiculo($empresa, 'PUB111', 'PUBLICO');
        $this->tarjeta($empresa, $publico);

        $filas = app(VehicleReportService::class)->allForExport([])->keyBy('vehicle_license_plate');

        $this->assertTrue($filas['PAR111']['es_particular']);
        $this->assertSame('2027-01-01', $filas['PAR111']['soat_expiry']);
        $this->assertNull($filas['PAR111']['rcc_expiry']);
        $this->assertNull($filas['PAR111']['operation_card_number']);
        $this->assertFalse($filas['PUB111']['es_particular']);
        $this->assertNotNull($filas['PUB111']['operation_card_number']);
    }
}
