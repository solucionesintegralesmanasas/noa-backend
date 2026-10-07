<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class InspectionPdfLayoutSmokeTest extends TestCase
{
    public function test_la_inspeccion_usa_el_layout_base(): void
    {
        $datos = $this->datosInspeccion();

        $html = view('pdf.fleet.vehicle-inspection', $datos)->render();

        // Encabezado SGFT04 propio, no el de 3 logos de la base.
        $this->assertStringContainsString('DIARIA DE VEHÍCULOS', $html);
        $this->assertStringContainsString('SGFT04', $html);
        $this->assertStringContainsString('SIN LOGO', $html);
        $this->assertStringNotContainsString('class="logo-header"', $html);
        // El 33.33% de la base no debe descuadrar el encabezado (revierte a auto).
        $this->assertStringContainsString('width: auto;', $html);
        // Sin logo no debe haber marca de agua rota.
        $this->assertStringNotContainsString('id="watermark"', $html);

        // Con logo de empresa sí sale de fondo (marca de agua de la base).
        $conLogo = view('pdf.fleet.vehicle-inspection', array_merge($datos, [
            'data' => ['logo' => 'iVBORw0KGgo=', 'firma' => null, 'firma_coordinador' => null],
        ]))->render();

        $this->assertStringContainsString('id="watermark"', $conLogo);
        // Sin franja de validación APTO/NO APTO.
        $this->assertStringNotContainsString('APTO PARA OPERAR', $html);
        $this->assertStringNotContainsString('Buenos:', $html);
        // Columna única OK como el formato original; la categoría fallida se señala con texto.
        $this->assertStringContainsString('REQUIERE ATENCIÓN', $html);
        $this->assertStringContainsString('(1/1 OK)', $html);
        $this->assertStringContainsString('(0/2 OK)', $html);
        // Solo el ítem respondido lleva X; los no contestados quedan vacíos.
        $this->assertSame(1, substr_count($html, 'answer-box">X'));
        $this->assertStringNotContainsString('check-box', $html);
        $this->assertStringNotContainsString('>B<', $html);
        // Espacio para fotografía del vehículo inspeccionado con renglones de descripción.
        $this->assertStringContainsString('FOTO DEL VEHÍCULO INSPECCIONADO', $html);
        $this->assertStringContainsString('FOTOGRAFÍA DEL VEHÍCULO', $html);
        $this->assertStringNotContainsString('UBICACIÓN DE DAÑOS', $html);
        // Datos del registro.
        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringContainsString('Sin novedades', $html);

        // Variante horizontal del layout: @page en apaisado.
        $horizontal = view('pdf.fleet.vehicle-inspection', $datos + ['orientation' => 'landscape'])->render();

        $this->assertStringContainsString('size: letter landscape', $horizontal);
        $this->assertStringContainsString('SGFT04', $horizontal);

        // Con membrete: banner en flujo y sin encabezado (lo reemplaza el membrete).
        $conMembrete = view('pdf.fleet.vehicle-inspection', $datos + [
            'orientation' => 'landscape',
            'letterhead' => 'data:image/png;base64,iVBORw0KGgo=',
        ])->render();

        $this->assertStringContainsString('id="letterhead-banner"', $conMembrete);
        $this->assertStringContainsString('with-letterhead', $conMembrete);
        // La sección propia se muestra aun con membrete (solo el header default se suprime).
        $this->assertStringContainsString('SGFT04</td>', $conMembrete);
        $this->assertStringNotContainsString('class="logo-header"', $conMembrete);
    }

    public function test_el_membrete_no_rompe_el_paginado_a_una_hoja(): void
    {
        $banner = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('img/transporte.png')));

        $this->assertSame(1, $this->contarPaginasPdf($this->datosInspeccion()));
        $this->assertSame(1, $this->contarPaginasPdf($this->datosInspeccion() + [
            'letterhead' => $banner,
        ]));
    }

    public function test_con_volumen_realista_sigue_en_una_hoja_vertical(): void
    {
        $datos = $this->datosInspeccion();
        $item = fn (string $nombre, string $status): object => (object) [
            'item' => (object) ['item_name' => $nombre],
            'status' => $status,
            'is_selected' => false,
        ];

        // 58 ítems en 6 categorías, como una inspección real cargada (2 columnas en vertical).
        $categorias = ['FRENOS', 'LUCES', 'LLANTAS', 'MOTOR', 'DOCUMENTOS', 'SEGURIDAD'];
        $agrupados = [];
        $columnas = [[], []];
        $conteos = [0, 0];
        foreach ($categorias as $i => $cat) {
            $agrupados[$cat] = [];
            $total = $i === 0 ? 13 : 9;
            for ($j = 1; $j <= $total; $j++) {
                $agrupados[$cat][] = $item("Ítem {$j} de {$cat}", $j === 3 && $i === 1 ? 'NO_APROBADO' : 'APROBADO');
            }
            $indice = array_search(min($conteos), $conteos, true);
            $columnas[$indice][] = $cat;
            $conteos[$indice] += $total + 2;
        }
        $datos['groupedResults'] = $agrupados;
        $datos['chunks'] = $columnas;

        $this->assertSame(58, collect($agrupados)->flatten(1)->count());
        $this->assertSame(1, $this->contarPaginasPdf($datos));
    }

    /** @param array<string, mixed> $datos */
    private function contarPaginasPdf(array $datos): int
    {
        $pdf = Pdf::loadView('pdf.fleet.vehicle-inspection', $datos)
            ->setPaper('letter', $datos['orientation'] ?? 'portrait');
        $pdf = app(\App\Services\Pdf\PdfService::class)->sellarPaginado($pdf);

        preg_match_all('~/Type\s*/Page([^s]|$)~', $pdf->output(), $coincidencias);

        return count($coincidencias[0]);
    }

    /** @return array<string, mixed> */
    private function datosInspeccion(): array
    {
        $licencia = (object) ['number' => 'ABC123', 'category' => 'C2'];
        $driver = new class($licencia)
        {
            public $first_name = 'Juan';

            public $last_name = 'Pérez';

            public $document_number = '123456';

            public function __construct(private object $licencia) {}

            public function licenciaActual(): object
            {
                return $this->licencia;
            }
        };
        $item = fn (string $nombre, string $status): object => (object) [
            'item' => (object) ['item_name' => $nombre],
            'status' => $status,
            'is_selected' => false,
        ];

        return [
            'title' => 'Inspección Preoperacional',
            'record' => (object) [
                'inspection_date' => '2026-10-01',
                'vehicle' => (object) [
                    'vehicle_license_plate' => 'ABC123',
                    'model' => '2020',
                    'brand' => (object) ['description' => 'Marca X'],
                ],
                'mileage' => 50000,
                'driver' => $driver,
                'inspector_name' => 'Inspector Y',
                'notes' => 'Sin novedades',
            ],
            'groupedResults' => [
                'FRENOS' => [$item('Frenos', 'APROBADO')],
                'LUCES' => [$item('Luces altas', 'NO_APROBADO'), $item('Exploradoras', 'NO_APLICA')],
            ],
            'chunks' => [['FRENOS'], ['LUCES']],
            'data' => ['logo' => null, 'firma' => null, 'firma_coordinador' => null],
        ];
    }
}
