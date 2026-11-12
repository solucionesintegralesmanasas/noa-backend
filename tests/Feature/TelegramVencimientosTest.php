<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailNotificationLog;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Notifications\DocumentosVencidosTelegramNotification;
use App\Services\Notifications\EmailLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alertas de documentos vencidos por Telegram: destinatarios por empresa,
 * respaldo del .env, envío de prueba y canal independiente del correo.
 *
 * @author Darwin Montes
 */
class TelegramVencimientosTest extends TestCase
{
    use RefreshDatabase;

    private const EMPRESA = 'TRANSPORTES ESPECIALES SIN BARRERA S.A.S';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        config([
            'services.telegram-bot-api.token' => 'token-de-prueba',
            'services.telegram-bot-api.chat_id' => null,
        ]);
        // Miércoles 10:00: dentro de la franja crítica de la mañana.
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function empresa(array $config = []): string
    {
        $empresa = $this->insertar('companies', [
            'business_name' => self::EMPRESA,
            'is_active' => true,
        ]);

        $this->insertar('system_configuration', $config + [
            'company_uuid' => $empresa,
            'activate_notifications' => true,
            'notify_by_email' => false,
            'notify_by_telegram' => true,
        ]);

        request()->attributes->set('current_company_uuid', $empresa);

        return $empresa;
    }

    /**
     * Vehículo propio con un SOAT vencido ($dias < 0) o que vence hoy (0).
     *
     * @return array{vehiculo: string, soat: string}
     */
    private function vehiculoConSoat(string $empresa, int $dias): array
    {
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $empresa,
            'vehicle_license_plate' => 'TGT'.random_int(100, 999),
            'is_active' => true,
        ]);

        $this->insertar('operation_cards', [
            'vehicle_uuid' => $vehiculo,
            'company_uuid' => $empresa,
            'operating_card_number' => 'TO-TELEGRAM',
            'affiliated_company' => self::EMPRESA,
            'expiration_date' => '2027-12-31',
            'status' => 1,
        ]);

        $soat = $this->insertar('vehicle_documents', [
            'vehicle_uuid' => $vehiculo,
            'company_uuid' => $empresa,
            'document_type' => 'SOAT',
            'expiry_date' => Carbon::today()->addDays($dias)->toDateString(),
            'status' => 'VIGENTE',
        ]);

        return ['vehiculo' => $vehiculo, 'soat' => $soat];
    }

    private function usuario(string $empresa): User
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Telegram',
            'email' => 'admin.telegram@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresa,
            'third_party_uuid' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function chatsNotificados(): array
    {
        $chats = [];

        Notification::assertSentOnDemand(
            DocumentosVencidosTelegramNotification::class,
            function ($notification, $channels, AnonymousNotifiable $notifiable) use (&$chats): bool {
                $chats[] = $notifiable->routes['telegram'] ?? null;

                return true;
            }
        );

        return array_values(array_filter($chats));
    }

    public function test_el_digest_envia_a_todos_los_chats_configurados(): void
    {
        $empresa = $this->empresa([
            'notification_telegram_chat_ids' => '111111111, 222222222,111111111',
        ]);
        $this->vehiculoConSoat($empresa, -3);

        app(EmailLogService::class)->notifyExpiringDocuments();

        $this->assertEqualsCanonicalizing(['111111111', '222222222'], $this->chatsNotificados());
        Mail::assertNothingSent();
        $this->assertDatabaseHas('email_notification_logs', [
            'company_uuid' => $empresa,
            'status' => 'sent',
        ]);
    }

    public function test_el_digest_no_envia_si_el_interruptor_de_telegram_esta_apagado(): void
    {
        $empresa = $this->empresa([
            'notify_by_telegram' => false,
            'notification_telegram_chat_ids' => '111111111',
        ]);
        $this->vehiculoConSoat($empresa, -3);

        app(EmailLogService::class)->notifyExpiringDocuments();

        Notification::assertNothingSent();
    }

    public function test_usa_el_chat_de_respaldo_del_env_si_no_hay_configurados(): void
    {
        config(['services.telegram-bot-api.chat_id' => '333333333']);
        $empresa = $this->empresa(['notification_telegram_chat_ids' => null]);
        $this->vehiculoConSoat($empresa, -3);

        app(EmailLogService::class)->notifyExpiringDocuments();

        $this->assertSame(['333333333'], $this->chatsNotificados());
    }

    public function test_el_aviso_inmediato_envia_por_telegram_sin_correo(): void
    {
        $empresa = $this->empresa(['notification_telegram_chat_ids' => '444444444']);
        $datos = $this->vehiculoConSoat($empresa, 0);

        app(EmailLogService::class)->notifyExpiredNow(
            VehicleDocument::query()->where('uuid', $datos['soat'])->firstOrFail()
        );

        $this->assertSame(['444444444'], $this->chatsNotificados());
        $this->assertSame(1, EmailNotificationLog::query()
            ->where('entity_uuid', $datos['soat'])
            ->where('status', 'sent')
            ->count());
        Mail::assertNothingSent();
    }

    public function test_la_prueba_responde_422_sin_chat_id(): void
    {
        $empresa = $this->empresa(['notification_telegram_chat_ids' => null]);
        Sanctum::actingAs($this->usuario($empresa), ['*']);

        $this->withHeaders(['X-Company-UUID' => $empresa])
            ->postJson('/api/v1/telegram/test', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_la_prueba_envia_a_los_chats_escritos_en_el_formulario(): void
    {
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario($empresa), ['*']);

        $this->withHeaders(['X-Company-UUID' => $empresa])
            ->postJson('/api/v1/telegram/test', ['chat_ids' => '555555555, -666666666'])
            ->assertOk()
            ->assertJsonPath('data.enviados', ['555555555', '-666666666']);

        $this->assertEqualsCanonicalizing(['555555555', '-666666666'], $this->chatsNotificados());
    }

    public function test_la_prueba_rechaza_mas_de_cinco_chats(): void
    {
        $empresa = $this->empresa();
        Sanctum::actingAs($this->usuario($empresa), ['*']);

        $this->withHeaders(['X-Company-UUID' => $empresa])
            ->postJson('/api/v1/telegram/test', [
                'chat_ids' => '111111111,222222222,333333333,444444444,555555555,666666666',
            ])
            ->assertStatus(422);

        Notification::assertNothingSent();
    }
}
