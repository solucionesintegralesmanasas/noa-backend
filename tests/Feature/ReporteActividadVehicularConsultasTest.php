<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\VehicleActivityReportExport;
use App\Services\Pdf\PdfService;
use App\Services\Reports\VehicleActivityReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004 / SPEC-010..016: el reporte consolidado de actividad vehicular
 * y su exportación declaran su presupuesto de consultas y de memoria.
 * Crecer el volumen de actividad no debe aumentar el conteo de consultas.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class ReporteActividadVehicularConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10');
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa actividad', 'is_active' => true]);
        request()->attributes->set('current_company_uuid', $this->empresa);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sembrarActividad(int $n, string $prefijo, int $desde = 1): void
    {
        for ($i = $desde; $i < $desde + $n; $i++) {
            $placa = $prefijo.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $v = $this->insertar('vehicles', ['company_uuid' => $this->empresa, 'vehicle_license_plate' => $placa, 'is_active' => true]);
            $t = $this->insertar('third_parties', ['company_uuid' => $this->empresa, 'first_name' => 'Chofer', 'last_name' => $placa]);
            $p = $this->insertar('projects', ['company_uuid' => $this->empresa, 'project_name' => 'Proy '.$placa]);

            $this->insertar('project_driver_vehicles', [
                'project_uuid' => $p,
                'vehicle_uuid' => $v,
                'third_party_uuid' => $t,
                'is_active' => 1,
            ]);

            $this->insertar('vehicle_inspections', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $v,
                'inspection_date' => '2026-11-05',
                'mileage' => 45000 + $i,
            ]);

            $this->insertar('maintenance', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $v,
                'maintenance_type' => 'PREVENTIVA',
                'mileage' => 45100 + $i,
                'service_description' => 'Mantenimiento '.$placa,
                'maintenance_date' => '2026-11-05',
                'status' => 'Finalizado',
            ]);

            $this->insertar('fuel_logs', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $v,
                'date' => '2026-11-05',
                'total_spent' => 250 + $i,
            ]);

            $this->insertar('fuel_requests', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $v,
                'status' => 'APROBADA',
                'requested_at' => '2026-11-05 08:30:00',
                'code' => 'FR-'.$placa,
                'public_token' => 'TOK-'.$placa,
            ]);

            $hoja = $this->insertar('service_delivery_control_sheet', [
                'company_uuid' => $this->empresa,
                'service_date' => '2026-11-05',
                'project_uuid' => $p,
                'official_name_and_surname' => 'Funcionario '.$placa,
                'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            ]);

            $this->insertar('service_internal_controls', [
                'company_uuid' => $this->empresa,
                'vehicle_uuid' => $v,
                'service_delivery_control_sheet_uuid' => $hoja,
            ]);
        }
    }

    public function test_el_reporte_paginado_tiene_conteo_constante_de_consultas(): void
    {
        $this->sembrarActividad(2, 'ACT');
        $filtros = [
            'company_uuid' => $this->empresa,
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
        ];

        $medir = fn () => app(VehicleActivityReportService::class)->paginateReport($filtros, 10, 1);
        $ampliar = fn () => $this->sembrarActividad(8, 'ACT', 3);

        $this->assertMedicionConDatos($medir()->total(), 'Reporte de actividad');
        $this->assertConteoConstante($medir, $ampliar, 'Reporte de actividad');
        // Presupuesto: 2 consultas fijas (1 COUNT agregado sobre la UNION + 1 SELECT paginado).
        $this->assertPresupuesto(2, $medir, 'Reporte de actividad');
    }

    public function test_la_exportacion_y_render_pdf_tienen_tope_de_consultas_y_memoria(): void
    {
        $this->sembrarActividad(10, 'EXP');
        $filtros = [
            'company_uuid' => $this->empresa,
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
        ];

        // 1. allForExport = exactamente 1 consulta constante
        $filas = null;
        $consultas = $this->contarConsultas(function () use ($filtros, &$filas) {
            $filas = app(VehicleActivityReportService::class)->allForExport($filtros, 100);
        });

        $this->assertMedicionConDatos($filas->count(), 'allForExport de actividad');
        $this->assertLessThanOrEqual(1, $consultas, "allForExport hizo {$consultas} consultas");

        // 2. Render PDF = 0 consultas directas adicionales
        memory_reset_peak_usage();
        $base = memory_get_usage();
        $pdf = null;
        $consultasPdf = $this->contarConsultas(function () use ($filas, $filtros, &$pdf) {
            $res = app(PdfService::class)->generateVehicleActivityReportPdf($filas, $filtros);
            $pdf = $res['pdf']->output();
        });
        $consumo = memory_get_peak_usage() - $base;

        $this->assertLessThanOrEqual(0, $consultasPdf, "Render PDF de actividad hizo {$consultasPdf} consultas");
        $this->assertStringStartsWith('%PDF', (string) $pdf);
        $this->assertLessThanOrEqual(25165824, $consumo, 'Render PDF superó 24 MB');

        // 3. Render Excel = 1 consulta (re-ejecuta allForExport) y tope de memoria
        memory_reset_peak_usage();
        $baseXls = memory_get_usage();
        $bytes = null;
        $consultasXls = $this->contarConsultas(function () use ($filtros, &$bytes) {
            $bytes = Excel::raw(
                new VehicleActivityReportExport($filtros, app(VehicleActivityReportService::class)),
                \Maatwebsite\Excel\Excel::XLSX
            );
        });
        $consumoXls = memory_get_peak_usage() - $baseXls;

        $this->assertNotEmpty($bytes);
        $this->assertLessThanOrEqual(1, $consultasXls, "Render Excel hizo {$consultasXls} consultas");
        $this->assertLessThanOrEqual(27262976, $consumoXls, 'Render Excel superó 26 MB');
    }
}

