<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvidenciaListadoTest extends TestCase
{
    use RefreshDatabase;

    private string $companyUuid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->companyUuid = (string) Str::uuid();
    }

    private function insertarPlanilla(array $datos): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('service_delivery_control_sheet')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $this->companyUuid,
            'service_date' => '2026-09-20',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'day_kind' => 'operacion',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $datos));
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function insertarFirma(
        int $entityId,
        string $entityType,
        string $role,
        string $scope,
        ?string $companyUuid = null
    ): void {
        Schema::disableForeignKeyConstraints();
        DB::table('signatures')->insert([
            'uuid' => (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'signer_role' => $role,
            'scope' => $scope,
            'status' => 'vigente',
            'signed_at' => now(),
            'company_uuid' => $companyUuid ?? $this->companyUuid,
            'path' => 'signatures/'.Str::uuid().'.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function insertarRecorrido(string $sheetUuid, bool $datosCompletos = true): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('service_delivery_control_sheet_routes')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'service_delivery_control_sheet_uuid' => $sheetUuid,
            'order_index' => 0,
            'origin' => 'Bodega',
            'destination' => 'Cliente',
            'funcionario_nombre' => $datosCompletos ? 'Ana Pérez' : null,
            'funcionario_cc' => $datosCompletos ? 'CC-1' : null,
            'end_time' => '17:00:00',
            'ending_kilometer' => '120',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function insertarRutaConUuid(string $sheetUuid): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('service_delivery_control_sheet_routes')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'service_delivery_control_sheet_uuid' => $sheetUuid,
            'order_index' => 0,
            'origin' => 'Origen',
            'destination' => 'Destino',
            'funcionario_nombre' => 'Ana Pérez',
            'funcionario_cc' => 'CC-1',
            'end_time' => '17:00:00',
            'ending_kilometer' => '120',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_dia_hijo_carga_rutas_y_firmas_en_lote_y_solo_se_certifica_completo(): void
    {
        $parentId = $this->insertarPlanilla([
            'uuid' => 'parent-completo',
            'service_date' => '2026-09-19',
            'is_active' => false,
        ]);
        $parentUuid = DB::table('service_delivery_control_sheet')->where('id', $parentId)->value('uuid');
        $childId = $this->insertarPlanilla([
            'uuid' => 'child-completo',
            'parent_uuid' => $parentUuid,
            'start_time' => '08:00:00',
            'service_date' => '2026-09-20',
            'is_active' => false,
        ]);
        $routeId = $this->insertarRecorrido('child-completo');
        $this->insertarFirma($routeId, ServiceDeliveryControlSheet::class.'Route', 'funcionario', 'recorrido');
        $this->insertarFirma($routeId, 'App\\Models\\ServiceDeliveryControlSheetRoute', 'conductor', 'recorrido');
        $this->insertarFirma($childId, 'App\\Models\\ServiceDeliveryControlSheetCoordinator', 'coordinador', 'planilla');

        $page = app(ServiceDeliveryControlSheetService::class)
            ->getAllServiceDeliveryControlSheetsWithPagination(15, 1, '', null, null, false);

        $parent = $page->getCollection()->firstWhere('id', $parentId);
        $child = $parent->children->firstWhere('id', $childId);

        $this->assertTrue($child->relationLoaded('routes'));
        $this->assertSame([], $child->firmas_pendientes);
        $this->assertTrue($child->operativamente_completa);
        $this->assertSame(ServiceDeliveryControlSheet::ESTADO_CERTIFICADA, $child->estado);
        $this->assertSame(ServiceDeliveryControlSheet::ESTADO_CERTIFICADA, $parent->estado);

        $json = json_decode(json_encode($page, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(ServiceDeliveryControlSheet::ESTADO_CERTIFICADA, $json['data'][0]['estado']);
        $this->assertSame([], $json['data'][0]['children'][0]['firmas_pendientes']);
        $this->assertSame(ServiceDeliveryControlSheet::ESTADO_CERTIFICADA, $json['data'][0]['children'][0]['estado']);
    }

    public function test_firma_de_coordinador_no_certifica_un_dia_con_recorrido_incompleto(): void
    {
        $parentId = $this->insertarPlanilla([
            'uuid' => 'parent-incompleto',
            'service_date' => '2026-09-19',
            'is_active' => false,
        ]);
        $parentUuid = DB::table('service_delivery_control_sheet')->where('id', $parentId)->value('uuid');
        $childId = $this->insertarPlanilla([
            'uuid' => 'child-incompleto',
            'parent_uuid' => $parentUuid,
            'start_time' => '08:00:00',
            'service_date' => '2026-09-20',
            'is_active' => false,
        ]);
        $routeId = $this->insertarRecorrido('child-incompleto', false);
        $this->insertarFirma($childId, 'App\\Models\\ServiceDeliveryControlSheetCoordinator', 'coordinador', 'planilla');

        $page = app(ServiceDeliveryControlSheetService::class)
            ->getAllServiceDeliveryControlSheetsWithPagination(15, 1, '', null, null, false);
        $child = $page->getCollection()->firstWhere('id', $parentId)->children->firstWhere('id', $childId);

        $this->assertSame(ServiceDeliveryControlSheet::ESTADO_PARCIAL, $child->estado);
        $this->assertFalse($child->operativamente_completa);
        $this->assertNotEmpty($child->firmas_pendientes);
    }

    public function test_resumen_del_proyecto_cuenta_todas_las_fechas_sin_depender_de_paginacion(): void
    {
        $projectUuid = (string) Str::uuid();
        $parentId = $this->insertarPlanilla([
            'uuid' => 'parent-summary',
            'project_uuid' => $projectUuid,
            'service_date' => '2026-09-19',
            'is_active' => false,
        ]);
        $parentUuid = DB::table('service_delivery_control_sheet')->where('id', $parentId)->value('uuid');

        foreach ([20, 21] as $day) {
            $this->insertarPlanilla([
                'uuid' => 'child-summary-'.$day,
                'parent_uuid' => $parentUuid,
                'project_uuid' => $projectUuid,
                'service_date' => '2026-09-'.$day,
                'day_kind' => 'disponibilidad',
                'availability_reason' => 'Mantenimiento',
                'start_time' => '08:00:00',
                'is_active' => false,
            ]);
        }

        $summary = app(ServiceDeliveryControlSheetService::class)->getProjectEvidenceSummary($projectUuid);

        $this->assertSame(2, $summary['total_dias']);
        $this->assertSame(2, $summary['cerrados']);
        $this->assertSame(2, $summary['evidencia_incompleta']);
        $this->assertSame(0, $summary['pendiente_certificacion']);
    }

    public function test_excepcion_aprobada_no_se_muestra_tambien_como_evidencia_incompleta(): void
    {
        $sheet = new ServiceDeliveryControlSheet;
        $sheet->id = 9876;
        $sheet->is_active = false;
        $sheet->exception_reason = 'Vía cerrada';
        $sheet->setRelation('routes', collect());

        $this->assertSame(
            [],
            app(ServiceDeliveryControlSheetService::class)->firmasPendientes($sheet)
        );
        $this->assertSame(
            ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION,
            $sheet->estadoAdministrativo(false, false)
        );
    }

    public function test_el_listado_no_agrega_una_consulta_por_cada_dia_hijo(): void
    {
        $parentId = $this->insertarPlanilla([
            'uuid' => 'parent-consultas',
            'service_date' => '2026-09-19',
            'is_active' => false,
        ]);
        $parentUuid = DB::table('service_delivery_control_sheet')->where('id', $parentId)->value('uuid');

        for ($day = 1; $day <= 12; $day++) {
            $uuid = 'child-consultas-'.$day;
            $this->insertarPlanilla([
                'uuid' => $uuid,
                'parent_uuid' => $parentUuid,
                'service_date' => sprintf('2026-09-%02d', $day),
                'start_time' => '08:00:00',
                'is_active' => false,
            ]);
            $routeId = $this->insertarRutaConUuid($uuid);
            $this->insertarFirma($routeId, 'App\\Models\\ServiceDeliveryControlSheetRoute', 'funcionario', 'recorrido');
            $this->insertarFirma($routeId, 'App\\Models\\ServiceDeliveryControlSheetRoute', 'conductor', 'recorrido');
            $childId = DB::table('service_delivery_control_sheet')->where('uuid', $uuid)->value('id');
            $this->insertarFirma($childId, 'App\\Models\\ServiceDeliveryControlSheetCoordinator', 'coordinador', 'planilla');
        }

        $selects = 0;
        $contar = true;
        DB::listen(function ($query) use (&$selects, &$contar): void {
            if ($contar && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selects++;
            }
        });

        $page = app(ServiceDeliveryControlSheetService::class)
            ->getAllServiceDeliveryControlSheetsWithPagination(15, 1, '', null, null, false);
        $contar = false;

        $parent = $page->getCollection()->firstWhere('id', $parentId);
        $this->assertCount(12, $parent->children);
        $this->assertSame(0, $parent->dias_evidencia_incompleta);
        $this->assertSame(0, $parent->dias_pendientes_certificacion);
        $this->assertLessThanOrEqual(20, $selects, 'La cantidad de SELECT debe ser acotada por lote, no crecer por cada hijo.');
    }
}
