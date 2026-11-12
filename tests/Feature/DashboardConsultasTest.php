<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004 / ARQ-014: el dashboard declara su presupuesto de consultas.
 * El conteo no crece con la cantidad de vehículos.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class DashboardConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    /**
     * Crea la empresa y autentica a un usuario suyo (fila en `company_user`).
     * En producción el filtro de empresa del dashboard lo pone el scope
     * `BelongsToCompany` a partir del usuario autenticado; sin usuario se mediría
     * el camino global (sin filtro), que no es el real.
     */
    private function empresa(): string
    {
        $empresa = $this->insertar('companies', ['business_name' => 'Empresa presupuesto', 'is_active' => true]);
        Schema::disableForeignKeyConstraints();
        $usuario = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prueba',
            'email' => Str::random(8) . '@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        DB::table('company_user')->insert([
            'user_id' => $usuario->id,
            'company_uuid' => $empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($usuario->fresh(), ['*']);
        // Lo que hace `SetCompanyContext` en cada petición; sin esto el scope recurre
        // a `companies()->first()` y suma una consulta por cada lectura de modelo.
        request()->attributes->set('current_company_uuid', $empresa);

        return $empresa;
    }

    private function vehiculo(string $empresa, string $placa): string
    {
        return $this->insertar('vehicles', ['company_uuid' => $empresa, 'vehicle_license_plate' => $placa, 'is_active' => true]);
    }

    /**
     * Empresa con un conductor autenticado (rol CONDUCTOR) y `$vehiculos` ligados
     * a él por proyectos, que es como el resumen del conductor los resuelve.
     *
     * @return array{empresa: string, conductor: string}
     */
    private function empresaConConductor(int $vehiculos): array
    {
        $empresa = $this->insertar('companies', ['business_name' => 'Empresa conductor', 'is_active' => true]);
        $correo = Str::random(8) . '@test.local';
        $conductor = $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'first_name' => 'Conductor',
            'last_name' => 'Prueba',
            'email' => $correo,
            'is_driver' => true,
        ]);

        Schema::disableForeignKeyConstraints();
        $usuario = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Conductor',
            'email' => $correo,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        DB::table('company_user')->insert([
            'user_id' => $usuario->id,
            'company_uuid' => $empresa,
            'third_party_uuid' => $conductor,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rol = Role::findOrCreate('CONDUCTOR', 'api');
        $usuario->assignRole($rol);

        $proyecto = $this->insertar('projects', ['company_uuid' => $empresa, 'project_name' => 'Proyecto conductor']);
        for ($i = 1; $i <= $vehiculos; $i++) {
            $v = $this->vehiculo($empresa, 'CON' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $this->insertar('project_driver_vehicles', [
                'project_uuid' => $proyecto,
                'third_party_uuid' => $conductor,
                'vehicle_uuid' => $v,
                'is_active' => true,
            ]);
        }

        Sanctum::actingAs($usuario->fresh(), ['*']);
        request()->attributes->set('current_company_uuid', $empresa);

        return ['empresa' => $empresa, 'conductor' => $conductor];
    }

    private function documento(string $empresa, string $vehiculo, string $tipo, string $vence): void
    {
        $this->insertar('vehicle_documents', [
            'company_uuid' => $empresa,
            'vehicle_uuid' => $vehiculo,
            'document_type' => $tipo,
            'expiry_date' => $vence,
            'status' => 'VIGENTE',
        ]);
    }

    public function test_el_resumen_no_crece_con_la_cantidad_de_vehiculos(): void
    {
        $empresa = $this->empresa();
        $this->vehiculo($empresa, 'DASH001');
        $this->vehiculo($empresa, 'DASH002');

        $medir = function (): array {
            Cache::flush();

            return app(DashboardService::class)->getSummary(30);
        };
        $ampliar = function () use ($empresa): void {
            for ($i = 3; $i <= 12; $i++) {
                $this->vehiculo($empresa, 'DASH' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            }
        };

        $this->assertConteoConstante($medir, $ampliar, 'Resumen del dashboard');
        // Cordura: el resumen se midió con vehículos de la empresa, no sobre vacío.
        $resumen = $medir();
        $enServicio = collect($resumen['stats'])->firstWhere('label', 'Vehículos en Servicio');
        $this->assertMedicionConDatos((int) ($enServicio['current'] ?? 0), 'Resumen del dashboard');
        // Medido: 4 consultas constantes (vehículos, km agregados en 1 pasada, conductores y FUEC).
        $this->assertPresupuesto(4, $medir, 'Resumen del dashboard');
    }

    public function test_las_alertas_no_crecen_con_la_cantidad_de_vehiculos(): void
    {
        $empresa = $this->empresa();
        foreach (['ALR001', 'ALR002'] as $placa) {
            $v = $this->vehiculo($empresa, $placa);
            $this->documento($empresa, $v, 'SOAT', Carbon::today()->addDays(3)->toDateString());
        }

        $medir = function (): void {
            Cache::flush();
            app(DashboardService::class)->getAlerts();
        };
        $ampliar = function () use ($empresa): void {
            for ($i = 3; $i <= 10; $i++) {
                $this->vehiculo($empresa, 'ALR' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            }
        };

        $this->assertConteoConstante($medir, $ampliar, 'Alertas del dashboard');
        // Cordura: hay alertas reales (documentos por vencer y faltantes), no una lista vacía.
        Cache::flush();
        $this->assertMedicionConDatos(count(app(DashboardService::class)->getAlerts()), 'Alertas del dashboard');
        // Medido: 12 consultas (una por lector de notificaciones, con lotes de 100).
        $this->assertPresupuesto(12, $medir, 'Alertas del dashboard');
    }

    public function test_el_resumen_del_conductor_no_crece_con_sus_vehiculos(): void
    {
        $this->empresaConConductor(1);

        $medir = function (): array {
            Cache::flush();

            return app(DashboardService::class)->getConductorSummary(30);
        };
        $ampliar = function (): void {
            $empresa = (string) request()->attributes->get('current_company_uuid');
            $conductor = (string) DB::table('company_user')->where('company_uuid', $empresa)->value('third_party_uuid');
            $proyecto = (string) DB::table('projects')->where('company_uuid', $empresa)->value('uuid');
            for ($i = 2; $i <= 8; $i++) {
                $v = $this->vehiculo($empresa, 'CON' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
                $this->insertar('project_driver_vehicles', [
                    'project_uuid' => $proyecto,
                    'third_party_uuid' => $conductor,
                    'vehicle_uuid' => $v,
                    'is_active' => true,
                ]);
            }
        };

        // Cordura: el rol CONDUCTOR resolvió el resumen (si no, devolvería []).
        $this->assertMedicionConDatos(count($medir()), 'Resumen del conductor');
        $this->assertConteoConstante($medir, $ampliar, 'Resumen del conductor');
        // Medido: 24 consultas (resolución del conductor + 4 lotes de kilometraje + vehículos).
        $this->assertPresupuesto(24, $medir, 'Resumen del conductor');
    }

    public function test_el_dashboard_no_usa_cache_y_aisla_por_empresa(): void
    {
        Cache::flush();
        $empresaA = $this->empresa();
        app(DashboardService::class)->getSummary(30);
        app(DashboardService::class)->getAlerts();

        $empresaB = $this->empresa();
        app(DashboardService::class)->getSummary(30);

        // Sin caché: el dashboard se computa en tiempo real sin almacenar claves en Cache
        $this->assertFalse(Cache::has("dashboard_summary_{$empresaA}_30"));
        $this->assertFalse(Cache::has("dashboard_alerts_{$empresaA}"));
        $this->assertFalse(Cache::has("dashboard_summary_{$empresaB}_30"));
        $this->assertFalse(Cache::has('dashboard_summary_global_30'));
    }

    public function test_los_endpoints_http_del_dashboard_responden_200_con_su_contrato_y_cero_escrituras(): void
    {
        $empresa = $this->empresa();
        $v = $this->vehiculo($empresa, 'HTTP001');
        // Documento vencido para ejercitar la rama de documentos expirados en alertas
        $this->documento($empresa, $v, 'SOAT', Carbon::today()->subDays(5)->toDateString());

        $headers = ['X-Company-UUID' => $empresa];

        // 1. Resumen general de la empresa
        DB::enableQueryLog();
        DB::flushQueryLog();

        $respuestaSummary = $this->getJson('/api/v1/dashboard/summary?days=30', $headers);
        $respuestaSummary->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats',
                ],
            ]);

        $this->assertCount(0, $this->mutacionesSql(), 'GET /dashboard/summary no debe realizar escrituras');

        // 2. Alertas del dashboard (no debe persistir cambios en BD durante la lectura)
        DB::flushQueryLog();

        $respuestaAlerts = $this->getJson('/api/v1/dashboard/alerts', $headers);
        $respuestaAlerts->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $alertas = $respuestaAlerts->json('data');
        $this->assertIsArray($alertas);
        $this->assertNotEmpty($alertas, 'Debe retornar alertas incluyendo el documento vencido');

        $this->assertCount(0, $this->mutacionesSql(), 'GET /dashboard/alerts no debe realizar escrituras');

        // 3. Resumen del conductor (autenticado como CONDUCTOR)
        $conductorData = $this->empresaConConductor(2);
        $conductorHeaders = ['X-Company-UUID' => $conductorData['empresa']];

        DB::flushQueryLog();

        $respuestaConductor = $this->getJson('/api/v1/dashboard/conductor-summary?days=30', $conductorHeaders);
        $respuestaConductor->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_service',
                    'conductor',
                    'kpis',
                    'quick_access',
                    'vehicles',
                ],
            ]);

        $this->assertCount(0, $this->mutacionesSql(), 'GET /dashboard/conductor-summary no debe realizar escrituras');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mutacionesSql(): array
    {
        return array_filter(
            DB::getQueryLog(),
            fn(array $query): bool => preg_match('/^(insert|update|delete|replace|alter|create|drop|truncate)\b/i', trim($query['query'])) === 1
        );
    }
}
