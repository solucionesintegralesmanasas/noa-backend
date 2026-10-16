<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class MaintenancePdfLayoutSmokeTest extends TestCase
{
    public function test_el_mantenimiento_usa_el_layout_base_en_vertical_con_membrete(): void
    {
        $datos = $this->datosMantenimiento();

        $html = view('pdf.maintenance.maintenance', $datos)->render();

        // Vertical por defecto y encabezado propio GL-FO-08 (no el de 3 logos).
        $this->assertStringContainsString('size: letter portrait', $html);
        $this->assertStringContainsString('GL-FO-08', $html);
        $this->assertStringContainsString('Hoja de Vida', $html);
        $this->assertStringNotContainsString('class="logo-header"', $html);
        // Sin texto dentro de la caja del logo (solo la imagen).
        $this->assertStringNotContainsString('Empresa de Transporte Especial', $html);
        // Marca de agua con el logo en alta solo sin membrete; con membrete
        // el banner ya identifica y la marca se suprime (patrón ficha técnica).
        $sinMembrete = array_merge($datos, ['letterhead' => null]);
        $htmlSinMembrete = view('pdf.maintenance.maintenance', $sinMembrete)->render();

        $this->assertStringContainsString('id="watermark"', $htmlSinMembrete);
        $this->assertStringNotContainsString('id="letterhead-banner"', $htmlSinMembrete);
        $this->assertStringContainsString('id="letterhead-banner"', $html);
        $this->assertStringContainsString('with-letterhead', $html);
        $this->assertStringNotContainsString('id="watermark"', $html);
        // Sin fila de Controlado.
        $this->assertStringNotContainsString('Controlado', $html);
        // Datos del vehículo y mantenimientos con X en su tipo.
        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringContainsString('Cambio de aceite', $html);
        $this->assertStringContainsString('Pastillas de freno', $html);
        $this->assertSame(2, substr_count($html, '<td>X</td>'));
        // Rellena hasta 15 filas: 2 con datos + 13 vacías.
        $this->assertSame(15, substr_count($html, '<td class="col-a">'));
    }

    public function test_el_mantenimiento_cabe_en_una_hoja_vertical(): void
    {
        $pdf = Pdf::loadView('pdf.maintenance.maintenance', $this->datosMantenimiento())
            ->setPaper('letter', 'portrait');

        preg_match_all('~/Type\s*/Page([^s]|$)~', $pdf->output(), $coincidencias);

        $this->assertSame(1, count($coincidencias[0]));
    }

    public function test_la_leyenda_de_generacion_es_vertical_a_la_derecha(): void
    {
        $pdf = Pdf::loadView('pdf.maintenance.maintenance', $this->datosMantenimiento())
            ->setPaper('letter', 'portrait');
        app(\App\Services\Pdf\PdfService::class)->sellarPaginado($pdf);
        $contenido = $pdf->output();

        // Descomprime los streams y verifica que el texto sale con matriz de rotación.
        preg_match_all('/stream\r?\n(.*?)endstream/s', $contenido, $m);
        $texto = null;
        foreach ($m[1] as $raw) {
            $data = @gzuncompress(trim($raw, "\r\n"));
            if ($data && str_contains($data, 'Generado por NOA Transportes')) {
                $texto = $data;
                break;
            }
        }

        $this->assertNotNull($texto, 'La leyenda no aparece en el PDF.');
        // El texto va en el stream con la tilde en codificación Windows ANSI.
        $this->assertStringContainsString('Generado por NOA Transportes', $texto);
        $this->assertStringContainsString('P', $texto);
        $this->assertMatchesRegularExpression('/gina 1 de 1\)\] TJ/', $texto);
        // Matriz de rotación: texto vertical (seno en los ejes, coseno en cero).
        $this->assertMatchesRegularExpression('/0\.000 1\.000 -1\.000 0\.000 [\d.]+ [\d.]+ Tm/', $texto);
    }

    /** @return array<string, mixed> */
    private function datosMantenimiento(): array
    {
        $mt = fn (string $fecha, string $tipo, string $detalle): object => (object) [
            'maintenance_date' => $fecha,
            'maintenance_type' => $tipo,
            'service_description' => $detalle,
            'workshop_name' => 'Taller Central',
            'mechanic_name' => 'Pedro Mecánico',
        ];

        return [
            'title' => 'Hoja de Vida – Mantenimiento',
            'orientation' => 'portrait',
            'letterhead' => 'data:image/png;base64,iVBORw0KGgo=',
            'vehicle' => (object) [
                'vehicle_license_plate' => 'ABC123',
                'brand' => (object) ['description' => 'Marca X'],
                'line' => 'Línea Y',
                'model' => '2020',
                'engine_displacement' => '2000cc',
                'color' => 'Blanco',
                'vehicleClass' => (object) ['description' => 'Bus'],
                'body_type' => 'Cerrada',
                'engine_number' => 'ENG123',
                'chassis_number' => 'CHS456',
                'transit_license_number' => 'LT789',
                'internal_number' => '42',
                'steering_type' => 'Hidráulica',
                'transmission_type' => 'Mecánica',
                'number_of_speeds' => '5',
                'bearing_type' => 'Bola',
                'rear_suspension' => 'Muelle',
                'number_of_tires' => '6',
                'rim_size' => '17.5',
                'rim_material' => 'Acero',
                'front_brake_type' => 'Disco',
                'rear_brake_type' => 'Tambor',
                'serial_number' => 'SER001',
                'number_of_windows' => '20',
                'seated_passenger_capacity' => '40',
            ],
            'company' => (object) ['business_name' => 'Transportes Test S.A.S.'],
            'maintenances' => [$mt('2026-09-01', 'PREVENTIVA', 'Cambio de aceite'), $mt('2026-09-15', 'CORRECTIVA', 'Pastillas de freno')],
            'company_logo_base64' => 'iVBORw0KGgo=',
            'company_logo_fondo_base64' => 'iVBORw0KGgo=',
        ];
    }
}
