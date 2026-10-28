<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Traits\BelongsToCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * Issue #2: Scope multi-tenant (BelongsToCompany) con fallo cerrado.
 * - Con contexto de empresa: filtra exclusivamente los registros de esa empresa.
 * - Sin contexto de empresa (flujo no exento): falla cerrado arrojando 403 / AccessDeniedHttpException
 *   y aplicando un candado SQL imposible (1 = 0) para nunca devolver datos de todas las empresas.
 * - Flujos legítimos exentos (cron, webcron, rutas públicas firmadas, withoutCompanyScope):
 *   permiten la consulta sin bloquear.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Limpiar request attributes entre pruebas
        request()->attributes->remove('current_company_uuid');
        request()->attributes->remove('exempt_from_company_context');

        // La deduplicación de advertencias es por proceso: se limpia para que cada prueba sea independiente.
        $registro = new ReflectionProperty(Vehicle::class, 'sinEmpresaRegistradas');
        $registro->setValue(null, []);
    }

    private function vehiculo(string $empresa, string $placa): Vehicle
    {
        Schema::disableForeignKeyConstraints();
        $uuid = (string) Str::uuid();
        DB::table('vehicles')->insert([
            'uuid' => $uuid,
            'company_uuid' => $empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $this->relleno());
        Schema::enableForeignKeyConstraints();

        return Vehicle::withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
    }

    /** Columnas obligatorias de vehicles sin valor por defecto. */
    private function relleno(): array
    {
        $datos = [];
        $columnas = DB::select(
            'select column_name n, data_type t, column_type ct from information_schema.columns
             where table_schema = database() and table_name = "vehicles" and is_nullable = "NO"
             and column_default is null and extra not like "%auto_increment%"'
        );
        foreach ($columnas as $c) {
            $datos[$c->n] = match (true) {
                in_array($c->t, ['char', 'varchar'], true) => str_ends_with($c->n, 'uuid') ? (string) Str::uuid() : 'x',
                in_array($c->t, ['text', 'longtext'], true) => 'x',
                $c->t === 'enum' => explode("','", trim(substr($c->ct, 5, -1), "'"))[0],
                $c->t === 'date' => '2026-09-30',
                in_array($c->t, ['datetime', 'timestamp'], true) => '2026-09-30 10:00:00',
                $c->t === 'json' => '[]',
                default => 0,
            };
        }

        return $datos;
    }

    public function test_con_contexto_de_empresa_solo_ve_los_datos_de_esa_empresa(): void
    {
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();
        $this->vehiculo($a, 'AAA111');
        $this->vehiculo($b, 'BBB222');

        request()->attributes->set('current_company_uuid', $a);

        $this->assertSame(['AAA111'], Vehicle::query()->pluck('vehicle_license_plate')->all());
    }

    public function test_sin_contexto_de_empresa_bloquea_con_403_y_no_devuelve_datos_de_todas_las_empresas(): void
    {
        $this->vehiculo((string) Str::uuid(), 'AAA111');
        $this->vehiculo((string) Str::uuid(), 'BBB222');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Acceso denegado: se requiere un contexto de empresa válido');

        // Sin contexto de empresa debe fallar cerrado arrojando excepción 403
        Vehicle::query()->get();
    }

    public function test_flujos_exentos_mediante_without_company_scope_pueden_consultar(): void
    {
        $this->vehiculo((string) Str::uuid(), 'AAA111');
        $this->vehiculo((string) Str::uuid(), 'BBB222');

        $resultados = BelongsToCompany::withoutCompanyScope(function () {
            return Vehicle::query()->get();
        });

        $this->assertCount(2, $resultados);
    }

    public function test_flujos_con_marca_explicita_de_exencion_pueden_consultar(): void
    {
        $this->vehiculo((string) Str::uuid(), 'AAA111');
        $this->vehiculo((string) Str::uuid(), 'BBB222');

        request()->attributes->set('exempt_from_company_context', true);

        $this->assertCount(2, Vehicle::query()->get());
    }

    public function test_el_trait_sigue_aplicado_a_los_modelos_de_negocio(): void
    {
        $this->assertContains(BelongsToCompany::class, class_uses_recursive(Vehicle::class));
    }
}
