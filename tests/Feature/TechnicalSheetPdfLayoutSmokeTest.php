<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class TechnicalSheetPdfLayoutSmokeTest extends TestCase
{
    public function test_la_ficha_tecnica_usa_el_layout_base(): void
    {
        $html = view('pdf.fleet.technical-sheet', $this->datosFicha())->render();

        // Vertical y layout base; título de documento.
        $this->assertStringContainsString('size: letter portrait', $html);
        $this->assertStringContainsString('FICHA TÉCNICA DE VEHÍCULO', $html);
        // Usa variables del estándar.
        $this->assertStringContainsString('var(--pdf-banda)', $html);
        // Datos del vehículo.
        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringContainsString('VIGENTE', $html);
        // El encabezado default de 3 logos no se usa (sección propia).
        $this->assertStringNotContainsString('class="logo-header"', $html);
    }

    public function test_la_ficha_tecnica_cabe_en_una_hoja_vertical(): void
    {
        $pdf = Pdf::loadView('pdf.fleet.technical-sheet', $this->datosFicha())
            ->setPaper('letter', 'portrait');

        preg_match_all('~/Type\s*/Page([^s]|$)~', $pdf->output(), $c);

        $this->assertSame(1, count($c[0]));
    }

    /** @return array<string, mixed> */
    private function datosFicha(): array
    {
        $doc = fn (string $tipo, string $num, string $venc, bool $pasado): object => (object) [
            'document_type' => $tipo,
            'policy_number' => $num,
            'expiry_date' => $venc,
            'esPasado' => $pasado,
        ];

        return [
            'vehicle' => (object) [
                'vehicle_license_plate' => 'ABC123',
                'internal_number' => '42',
                'brand' => (object) ['description' => 'Marca X'],
                'line' => 'Línea Y',
                'model' => '2020',
                'color' => 'Blanco',
                'vehicle_class' => (object) ['description' => 'Bus'],
                'body_type' => 'Cerrada',
                'fuel_type' => 'Diésel',
                'status' => true,
                'engine_number' => 'ENG123',
                'chassis_number' => 'CHS456',
                'vin_number' => 'VIN789',
                'engine_displacement' => '2000',
                'number_of_axles' => '2',
                'passenger_capacity' => '40',
                'seated_passenger_capacity' => '40',
                'third_party' => (object) [
                    'first_name' => 'Juan',
                    'last_name' => 'Pérez',
                    'trade_name' => null,
                    'type_of_document' => (object) ['prefix' => 'CC'],
                    'document_number' => '10203040',
                ],
                // El VENCIDO/VIGENTE lo determina la fatura via isPast(); el test
                // solo verifica el texto del doc, no la lógica de fechas.
                'vehicle_documents' => collect([$doc('SOAT', 'SOAT001', now()->addDay()->toDateString(), false)]),
                'operation_cards' => collect([(object) [
                    'operating_card_number' => 'TO-001',
                    'affiliated_company' => 'Transportes Test',
                    'expiration_date' => now()->addDay()->toDateString(),
                ]]),
            ],
            'data' => ['logo' => null, 'qrcode' => null],
        ];
    }
}