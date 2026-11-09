<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AuthorizeByResource;
use App\Http\Middleware\WebCronMiddleware;
use App\Models\License;
use App\Models\Notification;
use App\Models\SocialSecurityContribution;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fleet\SocialSecurityContributionService;
use App\Services\Fleet\VehicleService;
use App\Services\LicenseService;
use App\Services\Notifications\NotificationsService;
use App\Support\ResourceAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-014: Sincronización incremental de notificaciones y eliminación de candados por caché.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class SincronizacionIncrementalNotificacionesTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', [
            'business_name' => 'Empresa SPEC-014',
            'is_active' => true,
        ]);
        request()->attributes->set('current_company_uuid', $this->empresa);
    }

    private function crearVehiculoConDocumentos(string $placa, string $tipoServicio = 'PUBLICO', bool $activo = true): string
    {
        $vUuid = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => $placa,
            'type_of_service' => $tipoServicio,
            'is_active' => $activo,
            'registration_date' => Carbon::today()->subYears(3)->toDateString(),
        ]);

        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vUuid,
            'document_type' => 'SOAT',
            'expiry_date' => Carbon::today()->addDays(3)->toDateString(),
            'status' => 'VIGENTE',
        ]);

        if ($tipoServicio !== 'PARTICULAR') {
            $this->insertar('operation_cards', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $vUuid,
                'operating_card_number' => 'TO-' . $placa,
                'expiration_date' => Carbon::today()->addDays(3)->toDateString(),
                'status' => 1,
            ]);
        }

        return $vUuid;
    }

    /**
     * US1: La actualización de un vehículo ejecuta un número constante y mínimo
     * de consultas (≤ 6 consultas), sin disparar las 26 consultas del sync global.
     */
    public function test_actualizacion_de_vehiculo_tiene_tope_de_consultas_y_no_dispara_sync_completo(): void
    {
        // Sembrar 10 vehículos en la empresa
        $vehiculoUuid = '';
        for ($i = 1; $i <= 10; $i++) {
            $v = $this->crearVehiculoConDocumentos('VEC' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            if ($i === 5) {
                $vehiculoUuid = $v;
            }
        }

        $service = app(VehicleService::class);

        // Calentamiento
        $service->updateVehicle($vehiculoUuid, ['color' => 'BLANCO']);

        // Medir actualización de datos del vehículo
        $consultas = $this->contarConsultas(function () use ($service, $vehiculoUuid) {
            $service->updateVehicle($vehiculoUuid, ['color' => 'NEGRO']);
        });

        $this->assertLessThanOrEqual(6, $consultas, "La actualización del vehículo hizo $consultas consultas (presupuesto: ≤ 6)");
    }

    /**
     * US2: La actualización de seguridad social no ejecuta un bucle N+1
     * por conductor (conteo de consultas constante e independiente del volumen).
     */
    public function test_autoupdate_expired_statuses_seguridad_social_es_constante_sin_n_mas_uno(): void
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $pastMonth = Carbon::now()->subMonths(2)->startOfMonth();

        $crearConductorConAporte = function (int $id, bool $pagadoMesActual) use ($currentMonth, $pastMonth) {
            $tUuid = $this->insertar('third_parties', [
                'company_uuid' => $this->empresa,
                'first_name' => 'Conductor',
                'last_name' => 'Num ' . $id,
            ]);

            // Aporte del período anterior sin pago
            $this->insertar('social_security_contributions', [
                'third_party_uuid' => $tUuid,
                'billing_period' => $pastMonth->toDateString(),
                'payment_date' => null,
                'status' => 'PENDIENTE',
            ]);

            if ($pagadoMesActual) {
                // Aporte del mes actual pagado
                $this->insertar('social_security_contributions', [
                    'third_party_uuid' => $tUuid,
                    'billing_period' => $currentMonth->toDateString(),
                    'payment_date' => Carbon::now()->toDateString(),
                    'status' => 'PAGADO Y EN CURSO',
                ]);
            }
        };

        // Sembrar 2 conductores iniciales
        $crearConductorConAporte(1, false);
        $crearConductorConAporte(2, true);

        $service = app(SocialSecurityContributionService::class);

        $medir = fn() => $service->autoUpdateExpiredStatuses();
        $ampliar = function () use ($crearConductorConAporte) {
            for ($i = 3; $i <= 15; $i++) {
                $crearConductorConAporte($i, $i % 2 === 0);
            }
        };

        // Debe hacer el mismo número de consultas con 2 que con 15 conductores
        $this->assertConteoConstante($medir, $ampliar, 'autoUpdateExpiredStatuses seguridad social');
    }

    /**
     * US3: syncForVehicle actualiza de forma granular y exclusiva el vehículo dado,
     * sin tocar ni calcular las alertas de otros vehículos.
     */
    public function test_sync_for_vehicle_actualiza_alertas_solo_del_vehiculo_indicado(): void
    {
        $v1 = $this->crearVehiculoConDocumentos('GRN001');
        $v2 = $this->crearVehiculoConDocumentos('GRN002');

        $notifService = app(NotificationsService::class);

        // Sincronizar solo el vehículo 1
        $notifService->syncForVehicle($v1);

        $alertasV1 = Notification::where('company_uuid', $this->empresa)
            ->where('extra_data->vehicle_uuid', $v1)
            ->count();
        $alertasV2 = Notification::where('company_uuid', $this->empresa)
            ->where('extra_data->vehicle_uuid', $v2)
            ->count();

        $this->assertGreaterThan(0, $alertasV1, 'El vehículo 1 debe tener alertas generadas');
        $this->assertSame(0, $alertasV2, 'El vehículo 2 no debe tener alertas antes de su sync');

        // Al sincronizar el vehículo 2, sus alertas aparecen
        $notifService->syncForVehicle($v2);
        $alertasV2Despues = Notification::where('company_uuid', $this->empresa)
            ->where('extra_data->vehicle_uuid', $v2)
            ->count();
        $this->assertGreaterThan(0, $alertasV2Despues, 'El vehículo 2 ahora tiene sus alertas');
    }

    /**
     * SPEC-003 / SPEC-014: Un vehículo PARTICULAR no genera alertas de RCC/RCE
     * ni de tarjeta de operación en syncForVehicle.
     */
    public function test_sync_for_vehicle_respeta_regla_de_vehiculos_particulares(): void
    {
        $vParticular = $this->crearVehiculoConDocumentos('PAR001', 'PARTICULAR');

        $notifService = app(NotificationsService::class);
        $notifService->syncForVehicle($vParticular);

        $alertasTarjetas = Notification::where('company_uuid', $this->empresa)
            ->where('type', 'OPERATION_CARD')
            ->where('extra_data->vehicle_uuid', $vParticular)
            ->count();
        $this->assertSame(0, $alertasTarjetas, 'Un vehículo particular no debe generar alertas de tarjeta de operación');

        $alertasPolizas = Notification::where('company_uuid', $this->empresa)
            ->where('type', 'VEHICLE_DOCUMENT')
            ->where('extra_data->vehicle_uuid', $vParticular)
            ->whereIn('extra_data->document_type', ['RCC', 'RCE'])
            ->count();
        $this->assertSame(0, $alertasPolizas, 'Un vehículo particular no debe generar alertas de pólizas RCC/RCE');
    }

    /**
     * Si un vehículo pasa a inactivo, syncForVehicle limpia todas sus alertas.
     */
    public function test_sync_for_vehicle_limpia_alertas_si_vehiculo_pasa_a_inactivo(): void
    {
        $vUuid = $this->crearVehiculoConDocumentos('INA001', 'PUBLICO', true);
        $notifService = app(NotificationsService::class);

        $notifService->syncForVehicle($vUuid);
        $antes = Notification::where('company_uuid', $this->empresa)
            ->where('extra_data->vehicle_uuid', $vUuid)
            ->count();
        $this->assertGreaterThan(0, $antes);

        // Desactivar vehículo
        Vehicle::where('uuid', $vUuid)->update(['is_active' => false]);

        $notifService->syncForVehicle($vUuid);

        $despues = Notification::where('company_uuid', $this->empresa)
            ->where('extra_data->vehicle_uuid', $vUuid)
            ->count();
        $this->assertSame(0, $despues, 'Las alertas del vehículo inactivo deben eliminarse');
    }

    /**
     * US4: WebCronMiddleware no realiza consultas a la base de datos de caché.
     */
    public function test_webcron_middleware_no_hace_consultas_a_la_tabla_cache(): void
    {
        $middleware = app(WebCronMiddleware::class);
        $request = Request::create('/api/v1/health', 'GET');
        $response = new Response();

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $middleware->terminate($request, $response);

        $cacheQueries = array_filter($queries, fn($sql) => str_contains(strtolower($sql), 'cache'));
        $this->assertEmpty($cacheQueries, 'WebCronMiddleware ejecutó consultas sobre la tabla cache');
    }

    /**
     * US4: AuthorizeByResource en modo auditoría no realiza escrituras ni consultas en la tabla cache.
     */
    public function test_authorize_by_resource_en_auditoria_no_toca_la_tabla_cache(): void
    {
        config(['authorization.enforce' => false]);
        config(['authorization.enforce_catalogs' => false]);

        $user = new User([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario Auditoría',
            'email' => 'audit@x.test',
        ]);
        $user->exists = true;

        $reglas = app(ResourceAuthorization::class);
        $middleware = new AuthorizeByResource($reglas);

        $request = Request::create('/api/v1/vehicles', 'GET');
        $request->setUserResolver(fn() => $user);

        // Asignar ruta mockeada
        $route = new class {
            public function getName()
            {
                return 'api.v1.fleet.vehicles.index';
            }
            public function gatherMiddleware()
            {
                return [];
            }
        };
        $request->setRouteResolver(fn() => $route);

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $middleware->handle($request, fn() => new Response());

        $cacheQueries = array_filter($queries, fn($sql) => str_contains(strtolower($sql), 'cache'));
        $this->assertEmpty($cacheQueries, 'AuthorizeByResource ejecutó consultas sobre la tabla cache');
    }

    /**
     * US4: LicenseService recordOnlineVerification no escribe en la tabla cache.
     */
    public function test_licencia_verificacion_online_no_escribe_en_cache(): void
    {
        DB::table('licenses')->insert([
            'license_key' => 'KEY-TEST-014',
            'company_uuid' => $this->empresa,
            'status' => 'active',
            'online_verifications' => 0,
            'signature' => 'sig',
            'expiry_date' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(LicenseService::class);

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $service->recordOnlineVerification('KEY-TEST-014', $this->empresa);

        $cacheQueries = array_filter($queries, fn($sql) => str_contains(strtolower($sql), 'cache'));
        $this->assertEmpty($cacheQueries, 'recordOnlineVerification ejecutó consultas sobre la tabla cache');

        $verificaciones = DB::table('licenses')->where('license_key', 'KEY-TEST-014')->value('online_verifications');
        $this->assertSame(1, (int) $verificaciones);
    }

    /**
     * Endpoints de cron responden 200 con el token de seguridad autorizado.
     */
    public function test_endpoints_publicos_de_cron_notificaciones_y_seguridad_social(): void
    {
        config(['services.cron.secret' => 'secreto-cron-test']);

        $resNotif = $this->withHeaders([
            'X-Cron-Token' => 'secreto-cron-test',
        ])->getJson('/api/v1/public/cron/sync-notifications');

        $resNotif->assertOk()
            ->assertJson(['success' => true]);

        $resSs = $this->withHeaders([
            'X-Cron-Token' => 'secreto-cron-test',
        ])->getJson('/api/v1/public/cron/sync-social-security');

        $resSs->assertOk()
            ->assertJson(['success' => true]);
    }

    /**
     * US1b: Cambios en el tercero (afiliado) o placa re-sincronizan las alertas de forma incremental.
     */
    public function test_cambio_de_afiliado_o_placa_re_sincroniza_alertas_del_vehiculo(): void
    {
        $vUuid = $this->crearVehiculoConDocumentos('OLD123');
        app(NotificationsService::class)->syncForVehicle($vUuid);

        $alertaAntes = DB::table('notifications')
            ->where('extra_data->vehicle_uuid', $vUuid)
            ->where('type', 'VEHICLE_DOCUMENT')
            ->first();

        $this->assertNotNull($alertaAntes);
        $this->assertStringContainsString('OLD123', $alertaAntes->message);

        $nuevoTercero = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'first_name' => 'Nuevo',
            'last_name' => 'Afiliado',
        ]);

        $service = app(VehicleService::class);
        $service->updateVehicle($vUuid, [
            'vehicle_license_plate' => 'NEW456',
            'third_party_uuid' => $nuevoTercero,
        ]);

        $alertaDespues = DB::table('notifications')
            ->where('extra_data->vehicle_uuid', $vUuid)
            ->where('type', 'VEHICLE_DOCUMENT')
            ->first();

        $this->assertNotNull($alertaDespues);
        $this->assertStringContainsString('NEW456', $alertaDespues->message);
        $extraData = json_decode($alertaDespues->extra_data, true);
        $this->assertSame('NEW456', $extraData['vehicle_license_plate']);
    }
}
