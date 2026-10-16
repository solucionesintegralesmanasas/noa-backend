<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SPEC-002 §7.2 — Congelamiento verificado contra la base de datos real.
 *
 * Complementa los tests unitarios: aquí el registro existe de verdad, la guarda
 * lee is_active de MySQL y el rechazo atraviesa Eloquent.
 */
class CongelamientoPlanillaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function las_pruebas_corren_contra_mysql(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('noa_test', DB::connection()->getDatabaseName());
    }

    /**
     * Inserta una planilla mínima. Se desactivan las claves foráneas porque el
     * objeto de esta prueba es el congelamiento, no el grafo de empresa/proyecto.
     */
    private function crearPlanilla(bool $activa): ServiceDeliveryControlSheet
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('service_delivery_control_sheet')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => (string) Str::uuid(),
            'service_date' => '2026-09-20',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'day_kind' => 'operacion',
            'is_active' => $activa,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return ServiceDeliveryControlSheet::query()->findOrFail($id);
    }

    private function servicio(): ServiceDeliveryControlSheetService
    {
        return $this->app->make(ServiceDeliveryControlSheetService::class);
    }

    #[Test]
    #[DataProvider('operacionesContraPlanillaCerrada')]
    public function una_planilla_cerrada_no_admite_cambios(string $operacion, string $campo): void
    {
        $planilla = $this->crearPlanilla(false);
        $this->assertFalse($planilla->is_active);

        try {
            match ($operacion) {
                'update' => $this->servicio()->updateServiceDeliveryControlSheet($planilla->uuid, ['official_name_and_surname' => 'Intento de cambio']),
                'delete' => $this->servicio()->deleteServiceDeliveryControlSheet($planilla->uuid),
                'close' => $this->servicio()->closeServiceDeliveryControlSheet($planilla->uuid, []),
                'start' => $this->servicio()->startServiceDeliveryControlSheet($planilla->uuid, ['start_time' => '08:00:00']),
            };
            $this->fail("La operación '{$operacion}' debía rechazarse sobre una planilla cerrada.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors(), "El error debía apuntar a '{$campo}'.");
            $this->assertStringContainsString('cerrada', $e->errors()[$campo][0]);
        }

        // La evidencia permanece intacta: sigue cerrada y sin cambios.
        $this->assertFalse($planilla->fresh()->is_active);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function operacionesContraPlanillaCerrada(): array
    {
        return [
            'actualizar' => ['update', 'update'],
            'eliminar' => ['delete', 'delete'],
            'volver a cerrar' => ['close', 'close'],
            'volver a iniciar' => ['start', 'start'],
        ];
    }

    #[Test]
    public function una_planilla_abierta_si_admite_la_actualizacion(): void
    {
        $planilla = $this->crearPlanilla(true);

        $actualizada = $this->servicio()->updateServiceDeliveryControlSheet($planilla->uuid, [
            'official_name_and_surname' => 'Funcionario Actualizado',
        ]);

        $this->assertSame('Funcionario Actualizado', $actualizada->official_name_and_surname);
    }

    #[Test]
    public function una_planilla_abierta_si_se_puede_cerrar(): void
    {
        $planilla = $this->crearPlanilla(true);

        $cerrada = $this->servicio()->closeServiceDeliveryControlSheet($planilla->uuid, [
            'end_time' => '17:00:00',
        ]);

        $this->assertFalse($cerrada->is_active);
        $this->assertSame('operacion', $cerrada->day_kind);
    }
}
