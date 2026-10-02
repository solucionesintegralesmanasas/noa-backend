<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Dashboard\DashboardService;
use App\Services\Notifications\NotificationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004 / ARQ-014: el dashboard declara su presupuesto de consultas.
 * El conteo no crece con la cantidad de vehículos.
 */
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
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        DB::table('company_user')->insert([
            'user_id' => $usuario->id, 'company_uuid' => $empresa, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
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

    private function documento(string $vehiculo, string $tipo, string $vence): void
    {
        $this->insertar('vehicle_documents', [
            'vehicle_uuid' => $vehiculo, 'document_type' => $tipo, 'expiry_date' => $vence, 'status' => 'VIGENTE',
        ]);
    }

    public function test_el_resumen_no_crece_con_la_cantidad_de_vehiculos(): void
    {
        $empresa = $this->empresa();
        $this->vehiculo($empresa, 'DASH001');
        $this->vehiculo($empresa, 'DASH002');

        $medir = function (): void {
            Cache::flush();
            app(DashboardService::class)->getSummary(30);
        };
        $ampliar = function () use ($empresa): void {
            for ($i = 3; $i <= 12; $i++) {
                $this->vehiculo($empresa, 'DASH'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            }
        };

        $this->assertConteoConstante($medir, $ampliar, 'Resumen del dashboard');
        // Medido: 8 conteos del resumen (km, conductores, FUEC y vehículos, actual y anterior).
        $this->assertPresupuesto(14, $medir, 'Resumen del dashboard');
    }

    public function test_las_alertas_no_crecen_con_la_cantidad_de_vehiculos(): void
    {
        $empresa = $this->empresa();
        foreach (['ALR001', 'ALR002'] as $placa) {
            $v = $this->vehiculo($empresa, $placa);
            $this->documento($v, 'SOAT', '2026-12-31');
        }

        $medir = function (): void {
            Cache::flush();
            app(DashboardService::class)->getAlerts();
        };
        $ampliar = function () use ($empresa): void {
            for ($i = 3; $i <= 10; $i++) {
                $this->vehiculo($empresa, 'ALR'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            }
        };

        $this->assertConteoConstante($medir, $ampliar, 'Alertas del dashboard');
        // Medido: 12 consultas (una por lector de notificaciones, con lotes de 100).
        $this->assertPresupuesto(14, $medir, 'Alertas del dashboard');
    }

    public function test_la_cache_del_dashboard_es_por_empresa(): void
    {
        $empresaA = $this->empresa();
        app(DashboardService::class)->getSummary(30);
        app(DashboardService::class)->getAlerts();

        $empresaB = $this->empresa();
        app(DashboardService::class)->getSummary(30);

        // Antes la empresa salía de una columna inexistente y todas compartían "global".
        $this->assertTrue(Cache::has("dashboard_summary_{$empresaA}_30"));
        $this->assertTrue(Cache::has("dashboard_alerts_{$empresaA}"));
        $this->assertTrue(Cache::has("dashboard_summary_{$empresaB}_30"));
        $this->assertFalse(Cache::has('dashboard_summary_global_30'));
    }

    public function test_el_mantenimiento_preventivo_detecta_los_hitos_vencidos(): void
    {
        $empresa = $this->empresa();
        $vehiculo = $this->vehiculo($empresa, 'MTO001');
        $this->insertar('vehicle_inspections', ['vehicle_uuid' => $vehiculo, 'mileage' => 25000]);

        $alertas = app(NotificationsService::class)->notificationsForPreventativeMaintenance($empresa);
        $hitos = array_column(array_filter($alertas, fn ($a) => $a['status'] === 'VENCIDO'), 'milestone');
        sort($hitos);

        $this->assertSame([10000, 20000], $hitos);
    }
}
