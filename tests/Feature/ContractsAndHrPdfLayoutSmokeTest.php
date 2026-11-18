<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Smoke tests del estándar neutro (Inter + #d8d8d8/#f0f0f0/#f7f7f7) para Contrato Laboral y Contratos.
 */
class ContractsAndHrPdfLayoutSmokeTest extends TestCase
{
    public function test_contrato_laboral_usa_estandar_neutro_y_soporta_modos_de_marca(): void
    {
        $doc = [
            'title' => 'CONTRATO INDIVIDUAL DE TRABAJO A TÉRMINO INDEFINIDO',
            'intro' => 'Entre EMPLEADOR S.A.S. y EL TRABAJADOR se celebra el presente contrato.',
            'clauses' => [
                ['title' => 'PRIMERA - OBJETO', 'text' => 'Prestación de servicios como conductor.'],
                ['title' => 'SEGUNDA - SALARIO', 'text' => 'Remuneración mensual pactada conforme a la ley.'],
            ],
            'closing' => 'Para constancia se firma a los 18 días del mes de noviembre de 2026.',
            'signatures' => [
                'employer' => ['name' => 'REPRESENTANTE LEGAL', 'id' => 'CC 79.000.001', 'role' => 'Gerente General'],
                'employee' => ['name' => 'CARLOS MENDOZA', 'id' => 'CC 1020304050'],
            ],
        ];

        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $htmlFondo = view('pdf.hr.employment-contract', [
            'doc' => $doc,
            'letterhead' => null,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlFondo);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $htmlFondo);
        $this->assertStringContainsString('id="watermark"', $htmlFondo);
        $this->assertStringNotContainsString('Arial, sans-serif', $htmlFondo);

        $htmlLimpio = view('pdf.hr.employment-contract', [
            'doc' => $doc,
            'letterhead' => null,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => true,
        ])->render();

        $this->assertStringNotContainsString('id="watermark"', $htmlLimpio);
        $this->assertStringNotContainsString('id="letterhead-banner"', $htmlLimpio);

        $this->renderizarSinAvisos($htmlLimpio);
    }

    public function test_recibo_administracion_usa_esquema_neutro_sin_azules_ni_reset_universal(): void
    {
        $company = (object) [
            'business_name' => 'TRANSPORTES DE PRUEBA S.A.S.',
            'document_number' => '901555001',
            'verification_digit' => '4',
            'address' => 'Cra 10 # 20-30',
            'phone' => '3001112233',
            'email' => 'pagos@prueba.test',
            'web_page' => 'https://prueba.test',
            'municipality' => (object) ['name' => 'Bogotá D.C.'],
        ];

        $charge = (object) [
            'company' => $company,
            'vehicle' => (object) [
                'vehicle_license_plate' => 'GUX649',
                'internal_number' => '104',
                'passenger_capacity' => 28,
                'type_of_service' => 'PUBLICO',
                'model' => '2024',
                'line' => 'NPR',
                'brand' => (object) ['description' => 'CHEVROLET'],
                'vehicleClass' => (object) ['description' => 'BUSETA'],
                'thirdParty' => (object) [
                    'full_name' => 'PROPIETARIO DEMO',
                    'document_number' => '79123456',
                    'address' => 'Calle 5 # 10-12',
                    'phone' => '3109876543',
                    'email' => 'prop@prueba.test',
                    'municipality' => (object) ['name' => 'Cali'],
                ],
            ],
            'status' => 'PAGADO',
            'payment_method' => 'TRANSFERENCIA',
            'charge_type' => 'CUOTA_ADMINISTRACION',
            'currency_code' => 'COP',
            'period_date' => Carbon::parse('2026-11-01'),
            'payment_date' => Carbon::parse('2026-11-10'),
            'due_date' => Carbon::parse('2026-11-15'),
            'next_payment_date' => Carbon::parse('2026-12-05'),
            'amount' => 450000,
            'late_fee_percentage' => 0,
            'concept' => 'Administración mensual noviembre 2026',
            'notes' => null,
            'payment_reference' => 'REF-001',
            'bank_reference' => 'BANCO-998',
        ];

        $html = view('pdf.contracts.admin-charge', [
            'charge' => $charge,
            'data' => [
                'charge_internal_id' => 'RC-2026-0001',
                'legal_representative_name' => 'REP LEGAL',
                'document_number' => '79000001',
                'logo' => null,
                'firma' => null,
                'qr_code' => null,
            ],
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $html);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $html);
        $this->assertStringContainsString('--pdf-encabezado: #f0f0f0', $html);
        $this->assertStringContainsString('class="header-table"', $html);
        $this->assertStringContainsString('class="info-table"', $html);
        $body = \Illuminate\Support\Str::after($html, '<body');
        $this->assertStringNotContainsString('#1e3a8a', $html);
        $this->assertStringNotContainsString('Courier New', $html);
        $this->assertStringNotContainsString('class="placa-badge"', $body);
        $this->assertStringNotContainsString('background-color: #dcfce7', $body);
        $this->assertStringNotContainsString('footer-bar', $html);
        $this->assertDoesNotMatchRegularExpression('/\*\s*\{[^}]*margin\s*:/i', $html);

        $this->renderizarSinAvisos($html);
    }

    public function test_convenio_colaboracion_usa_inter_y_no_emite_warnings_de_flex(): void
    {
        $html = view('pdf.contracts.business-agreement', [
            'data' => [
                'agreement_internal_id' => 'CONV-001',
                'legal_representative_name' => 'JUAN PÉREZ',
                'document_number' => '79111222',
                'document_number_expedition_place' => 'Bogotá D.C.',
                'company_name' => 'TRANSPORTES A S.A.S.',
                'nit' => '900111222',
                'rep_name' => 'MARÍA GÓMEZ',
                'rep_document_id' => '52333444',
                'contracting_entity_name' => 'TRANSPORTES B S.A.S.',
                'contracting_entity_nit' => '900555666',
                'signing_city' => 'Bogotá D.C.',
                'signing_date' => '2026-11-18',
                'vehicle' => [
                    'vehicle_license_plate' => 'GUX649',
                    'vehicle_model' => '2024',
                    'vehicle_brand' => 'CHEVROLET',
                    'vehicle_line' => 'NPR',
                    'vehicle_capacity' => '28',
                    'operational_card_number' => 'TO-12345',
                    'expiration_date' => '2027-06-30',
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $html);
        $this->assertStringContainsString('#d8d8d8', $html);
        $this->assertStringContainsString('#f0f0f0', $html);
        $this->assertStringNotContainsString('display: flex', $html);
        $this->assertStringNotContainsString('Arial, sans-serif', $html);

        $this->renderizarSinAvisos($html);
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
