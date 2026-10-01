<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VehicleExpiryDigestMail;
use App\Models\EmailNotificationLog;
use App\Services\Notifications\EmailLogService;
use App\Utils\OwnCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Correo de vencimientos (cron): documentos y tarjetas de operación de los vehículos
 * PROPIOS de la empresa llegan al correo configurado en system_configuration.
 *
 * Caso real: TRANSPORTES ESPECIALES SIN BARRERA S.A.S -> transespecialessinbarreras@gmail.com.
 * Un vehículo es propio si su tarjeta de operación activa más reciente está afiliada a un
 * nombre de la empresa (OwnCompany); los afiliados y terceros no reciben correo.
 */
class DigestVencimientosTest extends TestCase
{
    use RefreshDatabase;

    private const EMPRESA = 'TRANSPORTES ESPECIALES SIN BARRERA S.A.S';

    private const DESTINO = 'transespecialessinbarreras@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        OwnCompany::limpiarCache();
        // Miércoles 10:00: dentro de la franja de mañana (06-12) de los digest críticos.
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OwnCompany::limpiarCache();
        parent::tearDown();
    }

    /**
     * Inserta una fila rellenando las columnas obligatorias sin valor por defecto, para no
     * depender de campos irrelevantes para el caso.
     *
     * @param  array<string, mixed>  $datos
     */
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
                in_array($c->t, ['date'], true) => '2026-09-30',
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

    private function empresaConCorreo(array $config = []): string
    {
        $empresa = $this->insertar('companies', [
            'business_name' => self::EMPRESA,
            'is_active' => true,
        ]);

        $this->insertar('system_configuration', $config + [
            'company_uuid' => $empresa,
            'activate_notifications' => true,
            'notify_by_email' => true,
            'notification_email' => self::DESTINO,
        ]);

        return $empresa;
    }

    /**
     * Vehículo con tarjeta de operación afiliada a $afiliada y, opcionalmente, un SOAT.
     *
     * @return array{vehiculo: string, soat: ?string, tarjeta: string}
     */
    private function vehiculo(string $empresa, string $placa, string $afiliada, ?string $soatVence = null, array $extra = []): array
    {
        $vehiculo = $this->insertar('vehicles', array_merge([
            'company_uuid' => $empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
        ], $extra));

        $tarjeta = $this->insertar('operation_cards', [
            'vehicle_uuid' => $vehiculo,
            'company_uuid' => $empresa,
            'operating_card_number' => 'TO-'.$placa,
            'affiliated_company' => $afiliada,
            'expiration_date' => '2027-12-31',
            'status' => 1,
        ]);

        $soat = $soatVence ? $this->insertar('vehicle_documents', [
            'vehicle_uuid' => $vehiculo,
            'company_uuid' => $empresa,
            'document_type' => 'SOAT',
            'expiry_date' => $soatVence,
            'status' => 'VIGENTE',
        ]) : null;

        return ['vehiculo' => $vehiculo, 'soat' => $soat, 'tarjeta' => $tarjeta];
    }

    private function ejecutar(): void
    {
        app(EmailLogService::class)->notifyExpiringDocuments();
    }

    /** @return array<int, string> Placas incluidas en todos los correos enviados. */
    private function placasEnviadas(): array
    {
        return Mail::sent(VehicleExpiryDigestMail::class)
            ->flatMap(fn (VehicleExpiryDigestMail $m) => collect($m->items)->pluck('placa'))
            ->unique()->values()->all();
    }

    public function test_envia_al_correo_configurado_los_documentos_de_vehiculos_propios(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25'); // SOAT vencido

        $this->ejecutar();

        Mail::assertSent(VehicleExpiryDigestMail::class, 1);
        Mail::assertSent(VehicleExpiryDigestMail::class, function (VehicleExpiryDigestMail $m) {
            $placas = collect($m->items)->pluck('placa')->all();
            $documentos = collect($m->items)->pluck('documento')->implode('|');

            return $m->hasTo(self::DESTINO)
                && str_contains($m->asunto, self::EMPRESA)
                && in_array('AAA111', $placas, true)
                && str_contains($documentos, 'SOAT');
        });
    }

    public function test_reconoce_la_empresa_propia_con_variantes_de_mayusculas_y_acentos(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'BBB222', 'transportes especiales sin barrera s.a.s.', '2026-09-25');
        $this->vehiculo($empresa, 'CCC333', '  Transportes  Especiales Sin Bárrera S.A.S. ', '2026-09-25');
        $this->vehiculo($empresa, 'DSA444', 'Transportes Especiales Sin Barrera SAS', '2026-09-25');
        $this->vehiculo($empresa, 'ESA555', 'transportes especiales sin barrera s a s', '2026-09-25');

        $this->ejecutar();

        $this->assertEqualsCanonicalizing(['BBB222', 'CCC333', 'DSA444', 'ESA555'], $this->placasEnviadas());
    }

    public function test_reconoce_nombres_propios_adicionales_de_la_configuracion(): void
    {
        $empresa = $this->empresaConCorreo(['own_company_names' => json_encode(['TESB LTDA'])]);
        $this->vehiculo($empresa, 'DDD444', 'TESB LTDA', '2026-09-25');

        $this->ejecutar();

        $this->assertSame(['DDD444'], $this->placasEnviadas());
    }

    public function test_no_incluye_vehiculos_de_afiliados_ni_terceros(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');
        $this->vehiculo($empresa, 'ZZZ999', 'COOPERATIVA AFILIADA S.A.', '2026-09-25');

        $this->ejecutar();

        $this->assertSame(['AAA111'], $this->placasEnviadas());
    }

    public function test_incluye_la_tarjeta_de_operacion_cuando_esta_por_vencer(): void
    {
        $empresa = $this->empresaConCorreo();
        $datos = $this->vehiculo($empresa, 'EEE555', self::EMPRESA);
        DB::table('operation_cards')->where('uuid', $datos['tarjeta'])->update(['expiration_date' => '2026-10-02']); // 2 días

        $this->ejecutar();

        Mail::assertSent(VehicleExpiryDigestMail::class, function (VehicleExpiryDigestMail $m) {
            $item = collect($m->items)->firstWhere('placa', 'EEE555');

            return $item !== null
                && str_contains($item['documento'], 'Tarjeta de Operación #TO-EEE555')
                && $item['days_left'] === 2
                && $item['estado'] === 'POR VENCER';
        });
    }

    public function test_excluye_vehiculos_inactivos_y_documentos_reemplazados(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');
        $this->vehiculo($empresa, 'INA000', self::EMPRESA, '2026-09-25', ['is_active' => false]);
        $propio = $this->vehiculo($empresa, 'REE777', self::EMPRESA, '2026-09-20');
        DB::table('vehicle_documents')->where('uuid', $propio['soat'])->update(['status' => 'INACTIVA']);

        $this->ejecutar();

        $this->assertSame(['AAA111'], $this->placasEnviadas());
    }

    public function test_no_envia_si_el_correo_esta_deshabilitado_o_sin_destinatario(): void
    {
        $sinCorreo = $this->empresaConCorreo(['notify_by_email' => false]);
        $this->vehiculo($sinCorreo, 'AAA111', self::EMPRESA, '2026-09-25');

        $this->ejecutar();

        Mail::assertNothingSent();
    }

    public function test_no_envia_si_no_hay_nada_que_avisar(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2027-12-31'); // lejos de vencer

        $this->ejecutar();

        Mail::assertNothingSent();
    }

    public function test_el_digest_critico_no_se_repite_en_la_misma_franja(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');

        $this->ejecutar();
        $this->ejecutar(); // el webcron puede reintentar

        Mail::assertSent(VehicleExpiryDigestMail::class, 1);
        $this->assertSame(1, EmailNotificationLog::query()->where('status', 'sent')->count());
    }

    public function test_envia_un_segundo_digest_critico_en_la_tarde(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');

        $this->ejecutar();
        Carbon::setTestNow(Carbon::parse('2026-09-30 15:00:00'));
        $this->ejecutar();

        Mail::assertSent(VehicleExpiryDigestMail::class, 2);
    }

    public function test_fuera_de_franja_no_envia_el_digest_critico(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');
        Carbon::setTestNow(Carbon::parse('2026-09-30 22:30:00'));

        $this->ejecutar();

        Mail::assertNothingSent();
    }

    public function test_los_proximos_a_vencer_se_envian_una_vez_al_dia_en_cualquier_hora(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-10-03'); // faltan 3 días
        Carbon::setTestNow(Carbon::parse('2026-09-30 22:30:00'));

        $this->ejecutar();
        $this->ejecutar();

        Mail::assertSent(VehicleExpiryDigestMail::class, 1);
        $this->assertSame(['AAA111'], $this->placasEnviadas());
    }

    public function test_el_recordatorio_de_15_dias_se_envia_una_sola_vez(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-10-15'); // faltan 15 días

        $this->ejecutar();
        $this->ejecutar();

        Mail::assertSent(VehicleExpiryDigestMail::class, 1);
        Mail::assertSent(VehicleExpiryDigestMail::class, fn (VehicleExpiryDigestMail $m) => $m->hasTo(self::DESTINO)
            && str_contains($m->asunto, 'Recordatorio'));
    }

    public function test_un_fallo_de_una_empresa_no_impide_enviar_a_las_demas(): void
    {
        $rota = $this->empresaConCorreo(['notification_email' => '   ']); // destinatario vacío
        $this->vehiculo($rota, 'ROT000', self::EMPRESA, '2026-09-25');
        $sana = $this->empresaConCorreo();
        $this->vehiculo($sana, 'AAA111', self::EMPRESA, '2026-09-25');

        $this->ejecutar();

        $this->assertSame(['AAA111'], $this->placasEnviadas());
    }

    public function test_el_comando_programado_dispara_el_envio(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');

        $codigo = Artisan::call('fleet:notify-expiring-documents');

        $this->assertSame(0, $codigo);
        Mail::assertSent(VehicleExpiryDigestMail::class, fn (VehicleExpiryDigestMail $m) => $m->hasTo(self::DESTINO));
    }

    public function test_el_comando_webcron_ejecuta_el_digest(): void
    {
        $empresa = $this->empresaConCorreo();
        $this->vehiculo($empresa, 'AAA111', self::EMPRESA, '2026-09-25');

        Artisan::call('webcron:run');

        Mail::assertSent(VehicleExpiryDigestMail::class, fn (VehicleExpiryDigestMail $m) => $m->hasTo(self::DESTINO));
    }
}
