<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * SPEC-004, capa 3: el banco de volumen (`perf:bench`) se niega a tocar bases
 * que no sean desechables y es reproducible con su semilla fija.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class BancoVolumenTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_niega_a_correr_contra_una_base_que_no_es_de_pruebas(): void
    {
        foreach (['seed', 'run'] as $modo) {
            $this->artisan('perf:bench', ['modo' => $modo, '--base' => 'noa'])
                ->expectsOutputToContain('no es una base desechable')
                ->assertFailed();
        }
    }

    public function test_el_sembrado_es_reproducible_y_la_medicion_da_el_formato_esperado(): void
    {
        $this->artisan('perf:bench', ['modo' => 'seed', '--puntos' => 3000, '--alertas' => 300])->assertSuccessful();
        $huella = DB::table('driver_locations')->orderBy('uuid')->limit(50)->get(['uuid', 'latitude', 'recorded_at', 'company_uuid'])->toJson();

        $this->artisan('perf:bench', ['modo' => 'seed', '--puntos' => 3000, '--alertas' => 300])->assertSuccessful();
        $this->assertSame($huella, DB::table('driver_locations')->orderBy('uuid')->limit(50)->get(['uuid', 'latitude', 'recorded_at', 'company_uuid'])->toJson());
        $this->assertSame(3000, DB::table('driver_locations')->count());

        $this->artisan('perf:bench', ['modo' => 'run', '--sin-archivo' => true, '--corridas' => 1, '--escrituras' => 200])
            ->expectsOutputToContain('Q1 monitor')
            ->expectsOutputToContain('Q8 alertas')
            ->expectsOutputToContain('Escritura:')
            ->assertSuccessful();
        // La medición de escritura se revierte: no deja filas.
        $this->assertSame(3000, DB::table('driver_locations')->count());
    }

    public function test_sin_datos_pide_sembrar_primero(): void
    {
        $this->artisan('perf:bench', ['modo' => 'run', '--sin-archivo' => true])
            ->expectsOutputToContain('perf:bench seed')
            ->assertFailed();
    }

    public function test_rechaza_un_entorno_desconocido(): void
    {
        $this->artisan('perf:bench', ['modo' => 'seed', '--puntos' => 100, '--alertas' => 10])->assertSuccessful();
        $this->artisan('perf:bench', ['modo' => 'run', '--entorno' => 'staging', '--sin-archivo' => true])
            ->expectsOutputToContain('Entorno inválido')
            ->assertFailed();
    }

    public function test_el_entorno_se_registra_y_separa_los_archivos(): void
    {
        $this->artisan('perf:bench', ['modo' => 'seed', '--puntos' => 100, '--alertas' => 10])->assertSuccessful();

        File::spy();
        $this->artisan('perf:bench', ['modo' => 'run', '--entorno' => 'produccion', '--corridas' => 1, '--escrituras' => 10])
            ->assertSuccessful();
        File::shouldHaveReceived('put')->once()->withArgs(function (string $ruta, string $contenido): bool {
            return str_contains($ruta, 'bench-produccion-')
                && str_contains($contenido, '"entorno": "produccion"')
                && str_contains($contenido, '"laravel"')
                && str_contains($contenido, '"php"');
        });
    }
}
