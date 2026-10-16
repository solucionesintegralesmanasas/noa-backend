<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\VehicleReportExport;
use App\Services\Pdf\PdfService;
use App\Services\Reports\VehicleReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004: el reporte de vehículos y su exportación declaran su presupuesto
 * de consultas y de memoria. Crecer la flota no debe romperlos.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class ReporteVehiculosConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa reporte', 'is_active' => true]);
    }

    private function vehiculoCompleto(string $placa): void
    {
        $v = $this->insertar('vehicles', ['company_uuid' => $this->empresa, 'vehicle_license_plate' => $placa, 'is_active' => true]);
        $t = $this->insertar('third_parties', ['company_uuid' => $this->empresa, 'first_name' => 'Tercero', 'last_name' => $placa]);
        $p = $this->insertar('projects', ['company_uuid' => $this->empresa, 'project_name' => 'Proyecto '.$placa]);
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'document_type' => 'SOAT',
            'expiry_date' => '2026-12-31', 'status' => 'VIGENTE',
        ]);
        $this->insertar('operation_cards', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'operating_card_number' => 'TC-'.$placa,
            'expiration_date' => '2026-12-31', 'status' => true,
        ]);
        $this->insertar('business_collaboration_agreements', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'contracting_entity_name' => 'Entidad '.$placa,
            'agreement_internal_id' => 'CONV-'.$placa, 'expiry_date' => '2026-12-31', 'status' => true,
        ]);
        $this->insertar('project_driver_vehicles', [
            'vehicle_uuid' => $v, 'project_uuid' => $p,
            'third_party_uuid' => $t, 'is_active' => true,
        ]);
        $this->insertar('maintenance', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'maintenance_date' => '2026-09-01',
            'next_maintenance_date' => '2026-12-01', 'status' => 'Finalizado',
        ]);
    }

    private function sembrar(int $n, string $prefijo, int $desde = 1): void
    {
        for ($i = $desde; $i < $desde + $n; $i++) {
            $this->vehiculoCompleto($prefijo.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }
    }

    public function test_el_reporte_paginado_no_crece_con_la_flota(): void
    {
        $this->sembrar(2, 'REP');
        $medir = fn () => app(VehicleReportService::class)->paginateReport(['company_uuid' => $this->empresa], 15, 1);
        $ampliar = fn () => $this->sembrar(13, 'REP', 3);

        $this->assertMedicionConDatos($medir()->total(), 'Reporte de vehículos');
        $this->assertConteoConstante($medir, $ampliar, 'Reporte de vehículos');
        $this->assertPresupuesto(10, $medir, 'Reporte de vehículos'); // medido: 10 (conteo + página + 8 relaciones)
    }

    public function test_la_exportacion_tiene_tope_de_consultas_y_de_memoria(): void
    {
        $this->sembrar(50, 'EXP');

        // El pico de memoria es monótono en el proceso: se reinicia para que lo que
        // hayan consumido las pruebas anteriores (o el sembrado) no oculte el de la exportación.
        memory_reset_peak_usage();
        $base = memory_get_usage();
        $filas = null;
        $consultas = $this->contarConsultas(function () use (&$filas) {
            $filas = app(VehicleReportService::class)->allForExport(['company_uuid' => $this->empresa], 100);
        });
        $consumo = memory_get_peak_usage() - $base;

        $this->assertMedicionConDatos($filas->count(), 'Exportación de vehículos');
        $this->assertLessThanOrEqual(9, $consultas, "Exportación hizo $consultas consultas"); // medido: 9
        $this->assertLessThanOrEqual(16777216, $consumo, 'La exportación de 50 vehículos superó 16 MB'); // ver medición en AGENTS.md
    }

    /**
     * US9: presupuesto del RENDER real del PDF del reporte (hoy solo se mide
     * `allForExport`, no la generación del archivo).
     */
    public function test_el_pdf_del_reporte_tiene_tope_de_consultas_y_memoria(): void
    {
        $this->sembrar(50, 'ES');
        $filtros = ['company_uuid' => $this->empresa, 'filter_type' => 'document'];
        $filas = app(VehicleReportService::class)->allForExport($filtros, 100);

        memory_reset_peak_usage();
        $base = memory_get_usage();
        $pdf = null;
        $consultas = $this->contarConsultas(function () use ($filas, $filtros, &$pdf) {
            $result = app(PdfService::class)->generateVehicleReportPdf($filas, $filtros);
            $pdf = $result['pdf']->output(); // fuerza el render real de DomPDF
        });
        $consumo = memory_get_peak_usage() - $base;

        $this->assertMedicionConDatos($filas->count(), 'PDF de vehículos');
        $this->assertStringStartsWith('%PDF', (string) $pdf, 'El render del PDF no produjo un archivo válido');
        $this->assertLessThanOrEqual(0, $consultas, "El render del PDF hizo $consultas consultas"); // medido: 0
        $this->assertLessThanOrEqual(25165824, $consumo, 'El render del PDF superó el tope de memoria'); // medido: 21287416 (~20,3 MB); tope 24 MB
    }

    /**
     * US9: presupuesto del RENDER real del Excel del reporte (hoy solo se mide
     * `allForExport`, no la generación del archivo).
     */
    public function test_el_excel_del_reporte_tiene_tope_de_consultas_y_memoria(): void
    {
        $this->sembrar(50, 'XS');
        $filtros = ['company_uuid' => $this->empresa, 'filter_type' => 'document'];

        memory_reset_peak_usage();
        $base = memory_get_usage();
        $bytes = null;
        $consultas = $this->contarConsultas(function () use ($filtros, &$bytes) {
            // `Excel::raw` genera el .xlsx en memoria y ejercita collection():
            // re-corre `allForExport`, luego PhpSpreadsheet construye el archivo.
            $bytes = Excel::raw(
                new VehicleReportExport($filtros, app(VehicleReportService::class)),
                \Maatwebsite\Excel\Excel::XLSX
            );
        });
        $consumo = memory_get_peak_usage() - $base;

        $this->assertNotEmpty($bytes, 'El render del Excel no produjo salida');
        $this->assertLessThanOrEqual(9, $consultas, "La exportación Excel hizo $consultas consultas"); // medido: 9 (allForExport)
        $this->assertLessThanOrEqual(29360128, $consumo, 'La exportación Excel superó el tope de memoria'); // medido: 25018232 (~23,9 MB); tope 28 MB
    }
}
