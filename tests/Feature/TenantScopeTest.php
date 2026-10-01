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
use Tests\TestCase;

/**
 * Scope multi-tenant (BelongsToCompany): con contexto filtra por empresa; sin contexto no filtra
 * (falla abierto, a propósito por ahora) pero deja constancia en el log para poder cerrarlo.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // La deduplicación de advertencias es por proceso: se limpia para que cada prueba sea independiente.
        $registro = new ReflectionProperty(Vehicle::class, 'sinEmpresaRegistradas');
        $registro->setValue(null, []);
    }

    private function vehiculo(string $empresa, string $placa): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('vehicles')->insert([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $empresa,
            'vehicle_license_plate' => $placa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $this->relleno());
        Schema::enableForeignKeyConstraints();
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

    public function test_sin_contexto_no_filtra_pero_lo_registra_una_sola_vez(): void
    {
        $this->vehiculo((string) Str::uuid(), 'AAA111');
        $this->vehiculo((string) Str::uuid(), 'BBB222');

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $contexto) => str_contains($mensaje, 'sin contexto de empresa')
                && $contexto['modelo'] === Vehicle::class);

        $this->assertCount(2, Vehicle::query()->get());
        $this->assertCount(2, Vehicle::query()->get()); // segunda consulta: no vuelve a registrar
    }

    public function test_el_trait_sigue_aplicado_a_los_modelos_de_negocio(): void
    {
        $this->assertContains(BelongsToCompany::class, class_uses_recursive(Vehicle::class));
    }
}
