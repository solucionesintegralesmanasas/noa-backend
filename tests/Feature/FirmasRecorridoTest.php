<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\ServiceDeliveryControlSheetRoute;
use App\Models\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SPEC-002 §7.3 — Idempotencia de firmas por recorrido.
 *
 * Cubre el defecto que existía: un reenvío parcial borraba la firma que no
 * venía en el payload. Ahora cada rol se reemplaza solo a sí mismo.
 */
class FirmasRecorridoTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 válido en base64. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $companyUuid;

    protected function setUp(): void
    {
        parent::setUp();

        // Estas pruebas verifican la idempotencia de FIRMAS, no la integridad
        // del catálogo de empresas. Se desactivan los FK durante toda la prueba
        // para poder usar un company_uuid sintético sin reconstruir municipios,
        // regímenes y tipos de documento.
        Schema::disableForeignKeyConstraints();
        $this->companyUuid = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function planillaConRuta(): ServiceDeliveryControlSheet
    {
        $id = DB::table('service_delivery_control_sheet')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $this->companyUuid,
            'service_date' => '2026-09-20',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'day_kind' => 'operacion',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('service_delivery_control_sheet_routes')->insert([
            'uuid' => (string) Str::uuid(),
            'service_delivery_control_sheet_uuid' => DB::table('service_delivery_control_sheet')->where('id', $id)->value('uuid'),
            'order_index' => 0,
            'origin' => 'Bodega',
            'destination' => 'Cliente',
            'funcionario_nombre' => 'Ana Pérez',
            'funcionario_cc' => 'CC 1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ServiceDeliveryControlSheet::query()->with('routes')->findOrFail($id);
    }

    private function firmasDeRuta(int $routeId): array
    {
        return Signature::query()
            ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetRoute')
            ->where('entity_id', $routeId)
            ->get()
            ->groupBy('signer_role')
            ->map(fn ($group) => $group->sortByDesc('id')->first())
            ->all();
    }

    public function test_envio_parcial_conserva_la_firma_omitida(): void
    {
        $svc = $this->app->make(\App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService::class);
        $planilla = $this->planillaConRuta();
        $route = $planilla->routes->first();

        // 1. Cierre con las dos firmas.
        $svc->closeRoute($planilla->uuid, [
            'route_uuid' => $route->uuid,
            'funcionario_signature' => self::PNG,
            'conductor_signature' => self::PNG,
        ]);

        $firmas = $this->firmasDeRuta($route->id);
        $this->assertArrayHasKey(Signature::ROL_FUNCIONARIO, $firmas);
        $this->assertArrayHasKey(Signature::ROL_CONDUCTOR, $firmas);
        $primeraFirmaConductor = $firmas[Signature::ROL_CONDUCTOR]->id;

        // 2. Reenvío SOLO con la firma del funcionario (p. ej. el conductor la omitió).
        $svc->closeRoute($planilla->uuid, [
            'route_uuid' => $route->uuid,
            'funcionario_signature' => self::PNG,
        ]);

        $firmas = $this->firmasDeRuta($route->id);
        $this->assertArrayHasKey(
            Signature::ROL_FUNCIONARIO,
            $firmas,
            'La firma del funcionario debe seguir vigente tras el reenvío parcial.'
        );
        $this->assertArrayHasKey(
            Signature::ROL_CONDUCTOR,
            $firmas,
            'La firma del conductor NO debe borrarse por un reenvío parcial.'
        );
        $this->assertSame(
            $primeraFirmaConductor,
            $firmas[Signature::ROL_CONDUCTOR]->id,
            'La firma del conductor no debe reemplazarse si no viene en el envío.'
        );
    }

    public function test_reenvio_de_un_rol_marca_la_anterior_como_reemplazada(): void
    {
        $svc = $this->app->make(\App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService::class);
        $planilla = $this->planillaConRuta();
        $route = $planilla->routes->first();

        $svc->closeRoute($planilla->uuid, [
            'route_uuid' => $route->uuid,
            'conductor_signature' => self::PNG,
        ]);
        $primera = $this->firmasDeRuta($route->id)[Signature::ROL_CONDUCTOR]->id;

        $svc->closeRoute($planilla->uuid, [
            'route_uuid' => $route->uuid,
            'conductor_signature' => self::PNG,
        ]);

        $anterior = Signature::query()->where('id', $primera)->first();
        $this->assertSame(Signature::STATUS_REEMPLAZADA, $anterior->status);

        $vigente = $this->firmasDeRuta($route->id)[Signature::ROL_CONDUCTOR];
        $this->assertSame(Signature::STATUS_VIGENTE, $vigente->status);
        $this->assertNotSame($primera, $vigente->id);
    }
}
