<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Smoke tests del estándar neutro (Inter + #d8d8d8/#f0f0f0/#f7f7f7) en los PDFs de flota.
 */
class FleetPdfLayoutSmokeTest extends TestCase
{
    public function test_reporte_vehiculos_y_actividad_usan_estandar_neutro_sin_avisos(): void
    {
        $htmlVehiculos = view('pdf.fleet.vehicle-report', [
            'filas' => collect([
                [
                    'vehicle_license_plate' => 'ABC123',
                    'model' => '2025',
                    'vehicle_class' => 'BUSETA',
                    'body_type' => 'CERRADA',
                    'modality_label' => 'ESPECIAL',
                    'ownership_label' => 'PROPIO',
                    'branch_name' => 'Sede Principal',
                    'soat_expiry' => '2027-01-01',
                    'es_particular' => false,
                    'rcc_expiry' => '2027-01-01',
                    'rce_expiry' => '2027-01-01',
                    'rtm_expiry' => '2027-01-01',
                    'operation_card_expiry' => '2027-01-01',
                    'agreement_name' => null,
                ],
            ]),
            'filtros' => ['filter_type' => 'document'],
            'filtroDescripcion' => 'DOCUMENTOS',
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlVehiculos);
        $this->assertStringContainsString('#d8d8d8', $htmlVehiculos);
        $this->assertStringContainsString('#f0f0f0', $htmlVehiculos);
        $this->assertStringNotContainsString('* { margin: 0', $htmlVehiculos);
        $this->assertStringNotContainsString('#e9ecef', $htmlVehiculos);

        $htmlActividad = view('pdf.fleet.vehicle-activity-report', [
            'filas' => new Collection([
                (object) [
                    'event_date' => '2026-11-17 08:00:00',
                    'vehicle_license_plate' => 'ABC123',
                    'project_name' => 'Proyecto Demo',
                    'event_type' => 'Inspección',
                    'driver_name' => 'Juan Pérez',
                    'status_value' => 'APROBADA',
                ],
            ]),
            'filtros' => ['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-11-17'],
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlActividad);
        $this->assertStringContainsString('#d8d8d8', $htmlActividad);
        $this->assertStringNotContainsString('* { margin: 0', $htmlActividad);
        $this->assertStringNotContainsString('#e9ecef', $htmlActividad);

        $this->renderizarSinAvisos($htmlVehiculos);
        $this->renderizarSinAvisos($htmlActividad);
    }

    public function test_acta_entrega_y_hoja_vida_vehicular_usan_estandar_neutro_sin_avisos(): void
    {
        $htmlActa = view('pdf.fleet.handover-record', [
            'company' => ['business_name' => 'Transportes Sin Barreras S.A.S.'],
            'images' => ['logo' => null],
            'generation_date' => '17/11/2026',
            'vehiculo' => (object) ['placa' => 'ABC123'],
            'propietario' => (object) ['nombre' => 'Propietario Demo'],
            'entrega' => (object) ['fecha' => '17/11/2026'],
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlActa);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $htmlActa);
        $this->assertStringContainsString('--pdf-encabezado: #f0f0f0', $htmlActa);
        $this->assertStringContainsString('TSB', $htmlActa);
        $this->assertStringNotContainsString('LOGO<br>EMPRESA', $htmlActa);
        $this->assertStringNotContainsString('footer-info', $htmlActa);

        $htmlHistoria = view('pdf.fleet.vehicle-history', [
            'data' => [
                'vehicle' => ['vehicle_license_plate' => 'ABC123'],
                'company' => (object) ['business_name' => 'Transportes Sin Barreras S.A.S.'],
            ],
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlHistoria);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $htmlHistoria);
        $this->assertStringContainsString('--pdf-encabezado: #f0f0f0', $htmlHistoria);
        $this->assertStringNotContainsString('* { margin: 0', $htmlHistoria);
        $this->assertStringNotContainsString('#f5f5f5', $htmlHistoria);

        $this->renderizarSinAvisos($htmlActa);
        $this->renderizarSinAvisos($htmlHistoria);
    }

    public function test_membrete_fondo_y_limpio_respetan_las_reglas_de_logo_y_marca(): void
    {
        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $membrete = 'data:image/png;base64,' . $pngTiny;
        $baseDatos = [
            'data' => [
                'vehicle' => ['vehicle_license_plate' => 'ABC123'],
                'company' => (object) ['business_name' => 'Transportes Sin Barreras S.A.S.'],
            ],
            'images' => ['logo' => $pngTiny],
        ];

        // 1. Modo membrete: muestra #letterhead-banner, suprime watermark y no duplica el logo en cabecera
        $conMembrete = view('pdf.fleet.vehicle-history', $baseDatos + [
            'letterhead' => $membrete,
            'logo_fondo' => null,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('id="letterhead-banner"', $conMembrete);
        $this->assertStringContainsString('with-letterhead', $conMembrete);
        $this->assertStringNotContainsString('id="watermark"', $conMembrete);
        $this->assertStringNotContainsString('class="header-logo-img"', $conMembrete);

        // 2. Modo fondo (sin membrete): suprime #letterhead-banner, muestra #watermark y logo en cabecera
        $sinMembrete = view('pdf.fleet.vehicle-history', $baseDatos + [
            'letterhead' => null,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringNotContainsString('id="letterhead-banner"', $sinMembrete);
        $this->assertStringContainsString('id="watermark"', $sinMembrete);
        $this->assertStringContainsString('class="header-logo-img"', $sinMembrete);

        // 3. Modo limpio: oculta membrete, watermark y logo imagen de cabecera
        $limpio = view('pdf.fleet.vehicle-history', $baseDatos + [
            'letterhead' => null,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => true,
        ])->render();

        $this->assertStringNotContainsString('id="letterhead-banner"', $limpio);
        $this->assertStringNotContainsString('id="watermark"', $limpio);
        $this->assertStringNotContainsString('class="header-logo-img"', $limpio);

        // 4. Hoja membretada vertical completa (altura > ancho): fondo de página + oculta celda logo en cabecera
        $imgVertical = imagecreatetruecolor(100, 140);
        ob_start();
        imagepng($imgVertical);
        $verticalBase64 = base64_encode((string) ob_get_clean());
        imagedestroy($imgVertical);

        $hojaCompleta = view('pdf.fleet.vehicle-history', $baseDatos + [
            'letterhead' => 'data:image/png;base64,' . $verticalBase64,
            'logo_fondo' => null,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('id="letterhead-page-bg"', $hojaCompleta);
        $this->assertStringNotContainsString('id="letterhead-banner"', $hojaCompleta);
        $this->assertStringNotContainsString('class="header-logo-cell"', $hojaCompleta);
    }

    public function test_demas_pdfs_trabajados_soportan_membrete_completo_y_logo_exclusivo(): void
    {
        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $imgVertical = imagecreatetruecolor(100, 140);
        ob_start();
        imagepng($imgVertical);
        $hojaVertical = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        imagedestroy($imgVertical);

        // Ficha técnica del vehículo
        $vehiculo = (object) [
            'vehicle_license_plate' => 'ABC123',
            'company' => (object) ['business_name' => 'Transportes Demo S.A.S.', 'document_number' => '900111222'],
        ];
        $fichaMembrete = view('pdf.fleet.technical-sheet-vehicles', [
            'vehicle' => $vehiculo,
            'data' => ['logo' => $pngTiny],
            'letterhead' => $hojaVertical,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $fichaMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $fichaMembrete);

        $fichaLogo = view('pdf.fleet.technical-sheet-vehicles', [
            'vehicle' => $vehiculo,
            'data' => ['logo' => $pngTiny],
            'letterhead' => null,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('class="header-logo-img"', $fichaLogo);

        // Mantenimiento
        $mantMembrete = view('pdf.maintenance.maintenance', [
            'vehicle' => $vehiculo,
            'company' => $vehiculo->company,
            'maintenances' => collect(),
            'company_logo_base64' => $pngTiny,
            'letterhead' => $hojaVertical,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $mantMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $mantMembrete);

        // Inspección preoperacional
        $inspMembrete = view('pdf.fleet.vehicle-inspection', [
            'record' => (object) ['company' => $vehiculo->company, 'vehicle' => $vehiculo],
            'groupedResults' => collect(),
            'data' => ['logo' => $pngTiny],
            'letterhead' => $hojaVertical,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $inspMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $inspMembrete);

        // Acta de entrega
        $actaMembrete = view('pdf.fleet.handover-record', [
            'company' => ['business_name' => 'Transportes Demo S.A.S.'],
            'images' => ['logo' => $pngTiny],
            'vehiculo' => (object) ['placa' => 'ABC123'],
            'propietario' => (object) ['nombre' => 'Demo'],
            'entrega' => (object) ['fecha' => '18/11/2026'],
            'letterhead' => $hojaVertical,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $actaMembrete);
        $this->assertStringNotContainsString('class="logo-cell"', $actaMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $actaMembrete);

        // Reportes (con logo sin membrete y con membrete completo sin duplicar logo)
        $repLogo = view('pdf.fleet.vehicle-report', [
            'filas' => collect(),
            'filtros' => ['filter_type' => 'document'],
            'logo_data_url' => 'data:image/png;base64,' . $pngTiny,
            'letterhead' => null,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('alt="Logo"', $repLogo);

        $repMembrete = view('pdf.fleet.vehicle-report', [
            'filas' => collect(),
            'filtros' => ['filter_type' => 'document'],
            'logo_data_url' => 'data:image/png;base64,' . $pngTiny,
            'letterhead' => $hojaVertical,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $repMembrete);
        $this->assertStringNotContainsString('alt="Logo"', $repMembrete);

        $actMembrete = view('pdf.fleet.vehicle-activity-report', [
            'filas' => collect(),
            'filtros' => [],
            'logo_data_url' => 'data:image/png;base64,' . $pngTiny,
            'letterhead' => $hojaVertical,
            'ocultar_marca' => false,
        ])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $actMembrete);
        $this->assertStringNotContainsString('alt="Logo"', $actMembrete);

        $this->renderizarSinAvisos($fichaMembrete);
        $this->renderizarSinAvisos($mantMembrete);
        $this->renderizarSinAvisos($inspMembrete);
        $this->renderizarSinAvisos($actaMembrete);
        $this->renderizarSinAvisos($repMembrete);
        $this->renderizarSinAvisos($actMembrete);
    }

    private function renderizarSinAvisos(string $html): void
    {
        global $_dompdf_warnings;
        $_dompdf_warnings = [];

        $bytes = Pdf::loadHTML($html)->setPaper('letter', 'portrait')->output();

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame([], $_dompdf_warnings ?? [], 'DomPDF emitió avisos al renderizar');
    }
}
