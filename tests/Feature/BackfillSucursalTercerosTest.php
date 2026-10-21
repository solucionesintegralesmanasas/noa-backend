<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Relleno de la sede de los terceros anteriores a la columna `branch_uuid`.
 *
 * La migración es idempotente y solo toca filas con sede NULL: asigna la sede
 * principal activa de la empresa (o la primera activa si ninguna es principal)
 * y deja intactas las sedes ya guardadas. Sin sedes activas no inventa una.
 */
class BackfillSucursalTercerosTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private const MIGRACION = '2026_10_21_000006_backfill_branch_uuid_in_third_parties_table.php';

    private function ejecutarMigracion(): void
    {
        $migracion = require database_path('migrations/'.self::MIGRACION);
        $migracion->up();
    }

    private function crearEmpresa(): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.Str::random(5), 'municipality_uuid' => $relleno, 'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno, 'web_page' => 'https://e.test',
            'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function crearSede(string $empresa, string $nombre, bool $principal = false): string
    {
        $ciudad = $this->insertar('cities', ['name' => 'Ciudad '.$nombre]);

        return $this->insertar('branches', [
            'company_uuid' => $empresa,
            'name' => $nombre,
            'address' => 'Calle '.$nombre,
            'municipality_uuid' => $ciudad,
            'is_primary' => $principal ? 1 : 0,
            'status' => 1,
        ]);
    }

    public function test_asigna_la_sede_principal_sin_tocar_las_sedes_ya_guardadas(): void
    {
        $empresa = $this->crearEmpresa();
        $secundaria = $this->crearSede($empresa, 'Sede Secundaria');
        $principal = $this->crearSede($empresa, 'Sede Principal', true);

        $sinSede = $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => '80000001',
            'first_name' => 'SIN',
            'last_name' => 'SEDE',
            'email' => 'sin.sede@test.local',
        ]);
        $conSede = $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => '80000002',
            'first_name' => 'CON',
            'last_name' => 'SEDE',
            'email' => 'con.sede@test.local',
            'branch_uuid' => $secundaria,
        ]);

        $this->ejecutarMigracion();

        $this->assertSame(
            $principal,
            DB::table('third_parties')->where('uuid', $sinSede)->value('branch_uuid'),
        );
        $this->assertSame(
            $secundaria,
            DB::table('third_parties')->where('uuid', $conSede)->value('branch_uuid'),
        );
    }

    public function test_es_idempotente(): void
    {
        $empresa = $this->crearEmpresa();
        $principal = $this->crearSede($empresa, 'Sede Principal', true);
        $tercero = $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => '80000003',
            'first_name' => 'IDEM',
            'last_name' => 'POTENTE',
            'email' => 'idem@test.local',
        ]);

        $this->ejecutarMigracion();
        $this->ejecutarMigracion();

        $this->assertSame(
            $principal,
            DB::table('third_parties')->where('uuid', $tercero)->value('branch_uuid'),
        );
    }

    public function test_sin_sedes_activas_deja_la_sede_en_null(): void
    {
        $empresa = $this->crearEmpresa();
        $tercero = $this->insertar('third_parties', [
            'company_uuid' => $empresa,
            'person_type' => 'NATURAL',
            'document_number' => '80000004',
            'first_name' => 'SIN',
            'last_name' => 'SEDES',
            'email' => 'sin.sedes@test.local',
        ]);

        $this->ejecutarMigracion();

        $this->assertNull(
            DB::table('third_parties')->where('uuid', $tercero)->value('branch_uuid'),
        );
    }
}
