<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\Signature;
use App\Services\Pdf\ServiceControlSheetDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SPEC-002 §7.4 — Clasificación de bandas del diario.
 *
 * Cubre la lógica que mueve ServiceControlSheetDaily::armarBandas: un día
 * con faltantes operativos va a la banda de evidencia incompleta; un día
 * completo sin coordinador, a la de certificación; un día con excepción
 * aprobada, a la suya. Nunca dos a la vez.
 */
class DailyBandasTest extends TestCase
{
    use RefreshDatabase;

    private string $companyUuid;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        $this->companyUuid = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function planilla(array $attrs = []): ServiceDeliveryControlSheet
    {
        $id = DB::table('service_delivery_control_sheet')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $this->companyUuid,
            'service_date' => '2026-09-20',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'day_kind' => 'operacion',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return ServiceDeliveryControlSheet::query()->with('routes')->findOrFail($id);
    }

    private function ruta(string $sheetUuid, array $attrs = []): int
    {
        return DB::table('service_delivery_control_sheet_routes')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'service_delivery_control_sheet_uuid' => $sheetUuid,
            'order_index' => 0,
            'origin' => 'Bodega',
            'destination' => 'Cliente',
            'funcionario_nombre' => 'Ana Pérez',
            'funcionario_cc' => 'CC 1',
            'end_time' => '17:00:00',
            'ending_kilometer' => '10250',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function firma(string $entityType, int $entityId, string $rol, string $scope): void
    {
        DB::table('signatures')->insert([
            'uuid' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'signer_role' => $rol,
            'scope' => $scope,
            'status' => Signature::STATUS_VIGENTE,
            'signed_at' => now(),
            'company_uuid' => $this->companyUuid,
            'path' => 'signatures/test.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bandas(ServiceDeliveryControlSheet ...$hojas): array
    {
        return $this->app->make(ServiceControlSheetDaily::class)->armarBandas(collect($hojas));
    }

    public function test_dia_con_faltantes_operativos_va_a_evidence_incompleta(): void
    {
        $hoja = $this->planilla();
        // Recorrido sin hora de llegada ni firmas: faltantes operativos.
        $this->ruta($hoja->uuid, ['end_time' => null, 'ending_kilometer' => null]);
        $hoja = $hoja->fresh(['routes']);

        [$incompleta, $certificacion, $excepcion] = $this->bandas($hoja);

        $this->assertCount(1, $incompleta);
        $this->assertSame('20/09/2026', $incompleta[0]['fecha']);
        $this->assertNotEmpty($incompleta[0]['pendientes']);
        $this->assertSame([], $certificacion);
        $this->assertSame([], $excepcion);
    }

    public function test_dia_completo_sin_coordinador_va_solo_a_certificacion(): void
    {
        $hoja = $this->planilla();
        $routeId = $this->ruta($hoja->uuid);
        $this->firma(
            'App\\Models\\ServiceDeliveryControlSheetRoute',
            $routeId,
            Signature::ROL_FUNCIONARIO,
            'recorrido'
        );
        $this->firma(
            'App\\Models\\ServiceDeliveryControlSheetRoute',
            $routeId,
            Signature::ROL_CONDUCTOR,
            'recorrido'
        );
        $hoja = $hoja->fresh(['routes']);

        [$incompleta, $certificacion, $excepcion] = $this->bandas($hoja);

        $this->assertSame([], $incompleta);
        $this->assertSame(['20/09/2026'], $certificacion);
        $this->assertSame([], $excepcion);
    }

    public function test_dia_con_excepcion_no_duplica_otras_bandas(): void
    {
        $hoja = $this->planilla(['exception_reason' => 'Vía cerrada por derrumbe']);
        // Sin recorridos ni firmas: todo faltaría, pero la excepción manda.
        $hoja = $hoja->fresh(['routes']);

        [$incompleta, $certificacion, $excepcion] = $this->bandas($hoja);

        $this->assertSame([], $incompleta);
        $this->assertSame([], $certificacion);
        $this->assertCount(1, $excepcion);
        $this->assertSame('20/09/2026', $excepcion[0]['fecha']);
        $this->assertSame('Vía cerrada por derrumbe', $excepcion[0]['motivo']);
    }

    public function test_dia_completo_y_certificado_no_muestra_bandas(): void
    {
        $hoja = $this->planilla();
        $routeId = $this->ruta($hoja->uuid);
        $this->firma(
            'App\\Models\\ServiceDeliveryControlSheetRoute',
            $routeId,
            Signature::ROL_FUNCIONARIO,
            'recorrido'
        );
        $this->firma(
            'App\\Models\\ServiceDeliveryControlSheetRoute',
            $routeId,
            Signature::ROL_CONDUCTOR,
            'recorrido'
        );
        $this->firma(
            'App\\Models\\ServiceDeliveryControlSheetCoordinator',
            $hoja->id,
            Signature::ROL_COORDINADOR,
            'planilla'
        );
        $hoja = $hoja->fresh(['routes']);

        [$incompleta, $certificacion, $excepcion] = $this->bandas($hoja);

        $this->assertSame([], $incompleta);
        $this->assertSame([], $certificacion);
        $this->assertSame([], $excepcion);
    }
}
