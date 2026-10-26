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

    public function test_la_ficha_del_vehiculo_con_datos_completos_cabe_en_una_hoja_vertical(): void
    {
        $datos = $this->datosFicha();
        $datos['vehicle']->vehicle_documents = collect([
            (object) [
                'document_type' => 'Seguro Obligatorio (SOAT)',
                'policy_number' => 'SOAT-984210547',
                'issuing_entity' => 'Seguros del Estado S.A.',
                'expiry_date' => '2026-11-14',
            ],
            (object) [
                'document_type' => 'Revisión Técnico-Mecánica (RTM)',
                'policy_number' => 'CDA-049281-2025',
                'issuing_entity' => 'CDA del Sinú S.A.S.',
                'expiry_date' => '2026-10-02',
            ],
            (object) [
                'document_type' => 'Póliza Resp. Civil Contractual (RCC)',
                'policy_number' => 'RCC-88410294',
                'issuing_entity' => 'La Previsora S.A. Seguros',
                'expiry_date' => '2027-01-20',
            ],
            (object) [
                'document_type' => 'Póliza Extracontractual (RCE)',
                'policy_number' => 'RCE-88410295',
                'issuing_entity' => 'La Previsora S.A. Seguros',
                'expiry_date' => '2027-01-20',
            ],
        ]);
        $datos['vehicle']->operation_cards = collect([
            (object) [
                'operating_card_number' => 'TO-2024-009841',
                'affiliated_company' => 'TRANSPORTES TEST',
                'issue_date' => '2024-02-15',
                'expiration_date' => '2026-02-15',
            ],
        ]);
        $datos['vehicle']->business_collaboration_agreements = collect([
            (object) [
                'contracting_entity_name' => 'TRANSPORTES COLECTIVOS DEL VALLE S.A.',
                'agreement_internal_id' => 'CONV-COLAB-2025-018',
                'expiry_date' => '2027-01-15',
            ],
        ]);
        $datos['vehicle']->third_party->address = 'Calle 29 N° 14-38';
        $datos['vehicle']->third_party->municipality = (object) ['name' => 'Montería'];
        $datos['vehicle']->third_party->phone = '3117894521';

        $pdf = Pdf::loadView('pdf.fleet.technical-sheet-vehicles', $datos)
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