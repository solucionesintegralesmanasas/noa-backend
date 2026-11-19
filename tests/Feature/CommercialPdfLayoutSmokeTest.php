<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Smoke tests del estándar neutro (Inter + #d8d8d8/#f0f0f0/#f7f7f7) y soporte membrete-logo
 * para Factura Comercial, Cotización, Nota Crédito y Nota Débito.
 */
class CommercialPdfLayoutSmokeTest extends TestCase
{
    public function test_factura_y_cotizacion_usan_estandar_neutro_sin_etiquetas_ni_colores_legados(): void
    {
        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $empresa = $this->empresaDemo();
        $factura = $this->facturaDemo('FACTURA', 'FAC-2026-001');
        $cotizacion = $this->facturaDemo('COTIZACION', 'COT-2026-014');

        $htmlFactura = view('pdf.commercial-invoice', [
            'documento' => $factura,
            'empresa' => $empresa,
            'data' => ['logo' => $pngTiny, 'qr' => $pngTiny],
            'letterhead' => null,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlFactura);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $htmlFactura);
        $this->assertStringContainsString('--pdf-encabezado: #f0f0f0', $htmlFactura);
        $this->assertStringContainsString('FACTURA N°', $htmlFactura);
        $this->assertStringContainsString('class="header-table"', $htmlFactura);
        $this->assertStringContainsString('id="watermark"', $htmlFactura);
        $this->assertStringContainsString('class="header-logo-img"', $htmlFactura);
        $bodyFactura = \Illuminate\Support\Str::after($htmlFactura, '<body');
        $this->assertStringNotContainsString('#172554', $htmlFactura);
        $this->assertStringNotContainsString('#38bdf8', $htmlFactura);
        $this->assertStringNotContainsString('#fcd34d', $bodyFactura);
        $this->assertStringNotContainsString('Courier New', $htmlFactura);
        $this->assertStringNotContainsString('estado-aceptada', $htmlFactura);
        $this->assertStringNotContainsString('firma-badge', $htmlFactura);

        $htmlCotizacion = view('pdf.commercial-invoice', [
            'documento' => $cotizacion,
            'empresa' => $empresa,
            'data' => ['logo' => null, 'qr' => null],
            'letterhead' => null,
            'logo_fondo' => null,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('COTIZACIÓN N°', $htmlCotizacion);
        $this->assertStringContainsString('>TDN<', preg_replace('/\s+/', '', $htmlCotizacion) ?? '');
        $this->assertStringNotContainsString('>SB<', $htmlCotizacion);

        $this->renderizarSinAvisos($htmlFactura);
        $this->renderizarSinAvisos($htmlCotizacion);
    }

    public function test_factura_comercial_y_notas_respetan_modos_membrete_y_limpio(): void
    {
        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $imgVertical = imagecreatetruecolor(100, 140);
        ob_start();
        imagepng($imgVertical);
        $hojaVertical = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        imagedestroy($imgVertical);

        $empresa = $this->empresaDemo();
        $factura = $this->facturaDemo('FACTURA', 'FAC-2026-002');
        $nota = $this->notaDemo($factura);

        $facturaMembrete = view('pdf.commercial-invoice', [
            'documento' => $factura,
            'empresa' => $empresa,
            'data' => ['logo' => $pngTiny, 'qr' => null],
            'letterhead' => $hojaVertical,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('id="letterhead-page-bg"', $facturaMembrete);
        $this->assertStringNotContainsString('class="header-logo-img"', $facturaMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $facturaMembrete);
        $this->assertStringNotContainsString('id="watermark"', $facturaMembrete);

        $notaMembrete = view('pdf.commercial-note', [
            'nota' => $nota,
            'tipoNota' => 'CREDITO',
            'esCredito' => true,
            'empresa' => $empresa,
            'data' => ['logo' => $pngTiny, 'qr' => null],
            'letterhead' => $hojaVertical,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => false,
        ])->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $notaMembrete);
        $this->assertStringContainsString('id="letterhead-page-bg"', $notaMembrete);
        $this->assertStringNotContainsString('class="header-logo-img"', $notaMembrete);
        $this->assertStringNotContainsString('class="header-logo-cell"', $notaMembrete);
        $this->assertStringNotContainsString('id="watermark"', $notaMembrete);
        $this->assertStringNotContainsString('#1e3a8a', $notaMembrete);
        $this->assertStringNotContainsString('Courier New', $notaMembrete);
        $this->assertStringNotContainsString('estado-aceptada', $notaMembrete);

        $notaLimpia = view('pdf.commercial-note', [
            'nota' => $nota,
            'tipoNota' => 'DEBITO',
            'esCredito' => false,
            'empresa' => $empresa,
            'data' => ['logo' => $pngTiny, 'qr' => null],
            'letterhead' => null,
            'logo_fondo' => $pngTiny,
            'ocultar_marca' => true,
        ])->render();

        $this->assertStringNotContainsString('id="letterhead-page-bg"', $notaLimpia);
        $this->assertStringNotContainsString('id="watermark"', $notaLimpia);
        $this->assertStringNotContainsString('class="header-logo-img"', $notaLimpia);
        $this->assertStringNotContainsString('class="header-logo-cell"', $notaLimpia);
        $this->assertStringContainsString('NOTA DÉBITO', $notaLimpia);

        $this->renderizarSinAvisos($facturaMembrete);
        $this->renderizarSinAvisos($notaMembrete);
        $this->renderizarSinAvisos($notaLimpia);
    }

    private function empresaDemo(): object
    {
        return (object) [
            'business_name' => 'Transportes del Norte S.A.S.',
            'document_number' => '901555001',
            'verification_digit' => '4',
            'address' => 'Cra 15 # 40-20',
            'phone' => '3001112233',
            'email' => 'facturacion@norte.test',
            'municipality' => (object) ['name' => 'Montería', 'department' => (object) ['name' => 'Córdoba']],
            'taxRegime' => (object) ['name' => 'Responsable de IVA'],
            'enablingResolutions' => collect([(object) ['resolution_number' => 'HAB-2026-01', 'status' => true]]),
            'bankDetails' => collect([(object) ['bank_name' => 'Bancolombia', 'account_type' => 'Ahorros', 'account_number' => '123-456789-00', 'account_holder' => null, 'is_active' => true]]),
            'economicActivities' => collect([(object) ['activity_code' => '4921', 'activity_description' => 'Transporte terrestre especial', 'is_main_activity' => true]]),
        ];
    }

    private function facturaDemo(string $tipo, string $numero): object
    {
        return (object) [
            'document_type' => $tipo,
            'invoice_number' => $numero,
            'issue_date' => Carbon::parse('2026-11-18'),
            'due_date' => Carbon::parse('2026-12-18'),
            'created_at' => Carbon::parse('2026-11-18 10:30:00'),
            'electronic_status' => 'ACEPTADA_DIAN',
            'cufe' => $tipo === 'FACTURA' ? str_repeat('a1b2', 24) : null,
            'subtotal' => 1000000,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_withholding' => 0,
            'invoice_total' => 1000000,
            'amount_paid' => 0,
            'notes' => 'Servicio de transporte especial ejecutado conforme a planilla.',
            'terms' => null,
            'customer' => (object) [
                'full_name' => 'CLIENTE EMPRESARIAL S.A.S.',
                'document_number' => '800222333',
                'nit_check_digit' => '9',
                'documentType' => (object) ['prefix' => 'NIT'],
                'address' => 'Av. Siempre Viva 742',
                'email' => 'cuentas@cliente.test',
                'phone' => '3109998877',
                'municipality' => (object) ['name' => 'Bogotá D.C.', 'department' => (object) ['name' => 'Cundinamarca']],
                'taxResponsibility' => (object) ['code' => 'O-47'],
            ],
            'resolution' => (object) [
                'resolution_number' => '18764000012345',
                'start_date' => Carbon::parse('2026-01-01'),
                'end_date' => Carbon::parse('2027-01-01'),
                'invoice_prefix' => 'FAC',
                'invoice_start_number' => 1,
                'invoice_end_number' => 5000,
            ],
            'billingProvider' => (object) ['name' => 'Factus'],
            'paymentMethod' => (object) ['name' => 'Transferencia Bancaria', 'dian_code' => '42'],
            'project' => (object) ['name' => 'Contrato Operación Norte'],
            'items' => collect([
                (object) [
                    'order_index' => 1,
                    'description' => 'Servicio de transporte especial de personal ruta norte',
                    'quantity' => 1,
                    'unit_price' => 1000000,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'line_total' => 1000000,
                    'measurementUnit' => (object) ['code' => '94'],
                    'vehicle' => (object) ['vehicle_license_plate' => 'GUX649', 'internal_number' => '104'],
                    'fuec_uuid' => null,
                    'control_sheet_uuid' => '1111',
                ],
            ]),
        ];
    }

    private function notaDemo(object $factura): object
    {
        return (object) [
            'note_number' => 'NC-2026-0005',
            'issue_date' => Carbon::parse('2026-11-18'),
            'created_at' => Carbon::parse('2026-11-18 11:00:00'),
            'status' => 'EMITIDA',
            'electronic_status' => 'ACEPTADA_DIAN',
            'correction_concept_code' => '03',
            'cude' => str_repeat('c3d4', 24),
            'subtotal' => 200000,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_withholding' => 0,
            'total' => 200000,
            'observation' => 'Rebaja comercial acordada por ajuste de ruta.',
            'invoice' => $factura,
            'paymentMethod' => (object) ['name' => 'Transferencia Bancaria'],
            'items' => collect([
                (object) [
                    'description' => 'Ajuste por descuento en servicio',
                    'quantity' => 1,
                    'unit_price' => 200000,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'line_total' => 200000,
                    'measurementUnit' => (object) ['code' => '94'],
                ],
            ]),
        ];
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
