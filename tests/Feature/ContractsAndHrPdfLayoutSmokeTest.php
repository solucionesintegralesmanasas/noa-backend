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

        $htmlMembrete = view('pdf.hr.employment-contract', [
            'doc' => $doc,
            'letterhead' => $this->hojaVerticalDataUrl(),
            'logo_fondo' => null,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('id="letterhead-page-bg"', $htmlMembrete);
        $this->assertStringNotContainsString('id="watermark"', $htmlMembrete);

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
        $this->assertStringNotContainsString('#166534', $body);
        $this->assertStringNotContainsString('#991b1b', $body);
        $this->assertStringNotContainsString('#92400e', $body);
        $this->assertStringNotContainsString('footer-bar', $html);
        $this->assertDoesNotMatchRegularExpression('/\*\s*\{[^}]*margin\s*:/i', $html);

        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $baseRecibo = [
            'charge' => $charge,
            'data' => [
                'charge_internal_id' => 'RC-2026-0001',
                'legal_representative_name' => 'REP LEGAL',
                'document_number' => '79000001',
                'logo' => $pngTiny,
                'firma' => null,
                'qr_code' => null,
            ],
        ];
        $reciboFondo = view('pdf.contracts.admin-charge', $baseRecibo + ['letterhead' => null, 'logo_fondo' => $pngTiny, 'ocultar_marca' => false])->render();
        $this->assertStringContainsString('id="watermark"', $reciboFondo);
        $this->assertStringContainsString('class="header-logo-cell"', $reciboFondo);

        $reciboMembrete = view('pdf.contracts.admin-charge', $baseRecibo + ['letterhead' => $this->hojaVerticalDataUrl(), 'logo_fondo' => null, 'ocultar_marca' => false])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $reciboMembrete);
        $this->assertStringNotContainsString('id="watermark"', $reciboMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $reciboMembrete);

        $reciboLimpio = view('pdf.contracts.admin-charge', $baseRecibo + ['letterhead' => null, 'logo_fondo' => $pngTiny, 'ocultar_marca' => true])->render();
        $this->assertStringNotContainsString('id="watermark"', $reciboLimpio);
        $this->assertStringNotContainsString('class="header-logo-cell"', $reciboLimpio);

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

        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $baseConv = [
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
                'logo' => $pngTiny,
                'vehicle' => ['vehicle_license_plate' => 'GUX649', 'vehicle_model' => '2024', 'vehicle_brand' => 'CHEVROLET', 'vehicle_line' => 'NPR', 'vehicle_capacity' => '28', 'operational_card_number' => 'TO-12345', 'expiration_date' => '2027-06-30'],
            ],
        ];
        $convFondo = view('pdf.contracts.business-agreement', $baseConv + ['letterhead' => null, 'logo_fondo' => $pngTiny, 'ocultar_marca' => false])->render();
        $this->assertStringContainsString('id="watermark"', $convFondo);

        $convMembrete = view('pdf.contracts.business-agreement', $baseConv + ['letterhead' => $this->hojaVerticalDataUrl(), 'logo_fondo' => null, 'ocultar_marca' => false])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $convMembrete);
        $this->assertStringNotContainsString('id="watermark"', $convMembrete);

        $convLimpio = view('pdf.contracts.business-agreement', $baseConv + ['letterhead' => null, 'logo_fondo' => $pngTiny, 'ocultar_marca' => true])->render();
        $this->assertStringNotContainsString('id="watermark"', $convLimpio);

        $this->renderizarSinAvisos($html);
    }

    public function test_contrato_alquiler_usa_estandar_neutro_identidad_de_empresa_y_tres_modos(): void
    {
        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $company = (object) [
            'business_name' => 'TRANSPORTES DEL SINÚ S.A.S.',
            'document_number' => '900888777',
            'verification_digit' => '1',
        ];
        $contract = (object) [
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'contract_number' => '0007',
            'status' => 'active',
            'monthly_term' => 2,
            'monthly_fee' => 3500000,
            'start_date' => Carbon::parse('2026-11-01'),
            'end_date' => Carbon::parse('2026-12-31'),
            'company' => $company,
            'owner' => (object) ['owner_name' => 'PROPIETARIO EJEMPLO', 'document_number' => '79123456'],
            'vehicle' => (object) [
                'vehicle_license_plate' => 'GUX649',
                'model' => '2025',
                'passenger_capacity' => 24,
                'type_of_service' => 'ESPECIAL',
                'brand' => (object) ['description' => 'CHEVROLET'],
                'vehicleClass' => (object) ['description' => 'BUSETA'],
            ],
            'installments' => collect([
                (object) [
                    'installment_number' => 1,
                    'due_date' => Carbon::parse('2026-11-30'),
                    'gross_amount' => 3500000,
                    'previous_balance' => 0,
                    'discount_amount' => 0,
                    'net_amount' => 3500000,
                    'paid_amount' => 3500000,
                    'pending_balance' => 0,
                    'status' => 'paid',
                ],
                (object) [
                    'installment_number' => 2,
                    'due_date' => Carbon::parse('2026-12-31'),
                    'gross_amount' => 3500000,
                    'previous_balance' => 0,
                    'discount_amount' => 0,
                    'net_amount' => 3500000,
                    'paid_amount' => 0,
                    'pending_balance' => 3500000,
                    'status' => 'pending',
                ],
            ]),
            'discounts' => collect(),
        ];

        $base = [
            'contract' => $contract,
            'company' => $company,
            'totalCuotas' => 2,
            'canonTotal' => 7000000,
            'fechaEmision' => '19/11/2026',
            'logo_data_url' => 'data:image/png;base64,' . $pngTiny,
            'empresa_siglas' => 'TDS',
        ];

        $htmlFondo = view('pdf.rental-contract', $base + ['letterhead' => null, 'logo_fondo' => $pngTiny, 'ocultar_marca' => false])->render();
        $this->assertStringContainsString('Inter-Regular.ttf', $htmlFondo);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $htmlFondo);
        $this->assertStringContainsString('id="watermark"', $htmlFondo);
        $this->assertStringContainsString('class="header-logo-cell"', $htmlFondo);
        $this->assertStringContainsString('TRANSPORTES DEL SINÚ S.A.S.', $htmlFondo);
        $this->assertStringNotContainsString('Noa Solutions', $htmlFondo);
        $this->assertStringNotContainsString('NOA SOLUTIONS', $htmlFondo);
        $this->assertStringNotContainsString('#2c3e50', $htmlFondo);
        $this->assertStringNotContainsString('#34495e', $htmlFondo);

        $htmlMembrete = view('pdf.rental-contract', $base + ['letterhead' => $this->hojaVerticalDataUrl(), 'logo_fondo' => null, 'ocultar_marca' => false])->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $htmlMembrete);
        $this->assertStringNotContainsString('id="watermark"', $htmlMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $htmlMembrete);

        $htmlLimpio = view('pdf.rental-contract', $base + ['letterhead' => null, 'logo_fondo' => $pngTiny, 'ocultar_marca' => true])->render();
        $this->assertStringNotContainsString('id="watermark"', $htmlLimpio);
        $this->assertStringNotContainsString('class="header-logo-cell"', $htmlLimpio);

        $this->renderizarSinAvisos($htmlFondo);
    }

    private function hojaVerticalDataUrl(): string
    {
        $img = imagecreatetruecolor(100, 140);
        ob_start();
        imagepng($img);
        $base64 = base64_encode((string) ob_get_clean());
        imagedestroy($img);

        return 'data:image/png;base64,' . $base64;
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
