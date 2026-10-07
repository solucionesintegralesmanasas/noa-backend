<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\Pdf\PdfService;
use Tests\TestCase;

class TechSheetVehiclesPdfLayoutSmokeTest extends TestCase
{
    public function test_la_ficha_del_vehiculo_usa_el_layout_base(): void
    {
        $html = view('pdf.fleet.technical-sheet-vehicles', $this->datosFicha())->render();

        $this->assertStringContainsString('size: letter portrait', $html);
        $this->assertStringContainsString('FICHA TÉCNICA DEL VEHÍCULO', $html);
        // Usa el estándar del layout.
        $this->assertStringContainsString('var(--pdf-banda)', $html);
        // La leyenda la estampa el canvas, no el HTML (una sola).
        $this->assertStringNotContainsString('vertical-legend', $html);
        $this->assertStringNotContainsString('Generado por NOA', $html);
        // Datos del vehículo.
        $this->assertStringContainsString('ABC123', $html);
        // El encabezado default de 3 logos no se usa.
        $this->assertStringNotContainsString('class="logo-header"', $html);
    }

    public function test_la_ficha_del_vehiculo_cabe_en_una_hoja_vertical(): void
    {
        $pdf = Pdf::loadView('pdf.fleet.technical-sheet-vehicles', $this->datosFicha())
            ->setPaper('letter', 'portrait');
        app(PdfService::class)->sellarPaginado($pdf);

        preg_match_all('~/Type\s*/Page([^s]|$)~', $pdf->output(), $c);

        $this->assertSame(1, count($c[0]));
    }

    /** @return array<string, mixed> */
    private function datosFicha(): array
    {
        return [
            'title' => 'Ficha Técnica Vehículo',
            'vehicle' => (object) [
                'vehicle_license_plate' => 'ABC123',
                'internal_number' => '42',
                'vehicle_class' => (object) ['description' => 'Bus'],
                'brand' => (object) ['description' => 'Marca X'],
                'line' => 'Línea Y',
                'model' => '2020',
                'engine_displacement' => '2000',
                'number_of_axles' => '2',
                'type_of_service' => 'PÚBLICO',
                'company' => (object) ['business_name' => 'TRANSPORTES TEST', 'document_number' => '9001'],
                'business_collaboration_agreements' => collect(),
                'operation_cards' => collect(),
                'vehicle_documents' => collect(),
                'third_party' => (object) [
                    'first_name' => 'JUAN', 'last_name' => 'PEREZ',
                    'type_of_document' => (object) ['prefix' => 'CC'],
                    'document_number' => '1020', 'municipality' => null,
                ],
            ],
            'data' => ['logo' => null, 'qrcode' => null],
        ];
    }
}