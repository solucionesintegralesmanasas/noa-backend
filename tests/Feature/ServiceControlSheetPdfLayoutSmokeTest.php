<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class ServiceControlSheetPdfLayoutSmokeTest extends TestCase
{
    public function test_la_planilla_sale_en_carta_horizontal_con_datos_reales(): void
    {
        $data = $this->datos();

        $html = view('pdf.maintenance.service-control-sheet', $data)->render();

        $this->assertStringContainsString('size: letter landscape', $html);
        $this->assertStringContainsString('PLANILLA DE CONTROL DE PRESTACIÓN DE SERVICIOS', $html);
        // El pie derecho es la certificación del coordinador logístico, no el
        // nombre del funcionario/conductor ni la empresa transportadora.
        $this->assertStringContainsString('COORDINADOR LOGÍSTICO', $html);
        // Datos de la planilla, no los de la maqueta anterior.
        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringContainsString('Bodega - Cliente', $html);
        $this->assertStringContainsString('Carlos Pérez', $html);
        $this->assertStringContainsString('19:30', $html);
        $this->assertStringNotContainsString('WLG-482', $html);
        $this->assertStringNotContainsString('CONSORCIO MINERO DEL SINÚ', $html);
        // Tabla multipágina: la cabecera se repite.
        $this->assertStringContainsString('display: table-header-group', $html);
        // Márgenes de impresión reales: DomPDF descarta el margen de @page si
        // una regla universal resetea el `margin` (el contenido tocaría el borde
        // de la hoja y la impresora lo recortaría).
        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*margin:\s*6mm 7mm 6mm 7mm/s', $html);
        $this->assertDoesNotMatchRegularExpression('/\*\s*\{[^}]*margin\s*:/s', $html);
        // Sin marca de agua: DomPDF no aplica `opacity` y el logo tapaba la tabla.
        $this->assertStringNotContainsString('watermark', $html);

        $pdf = Pdf::loadView('pdf.maintenance.service-control-sheet', $data)
            ->setPaper('letter', 'landscape');
        $contenido = $pdf->output();

        preg_match('~/MediaBox\s*\[([^\]]+)\]~', $contenido, $mediaBox);
        $this->assertSame(
            '0.000 0.000 792.000 612.000',
            preg_replace('/\s+/', ' ', trim($mediaBox[1] ?? '')),
            'La planilla debe ser carta horizontal (792 x 612 pt).'
        );

        preg_match_all('~/Type\s*/Page([^s]|$)~', $contenido, $paginas);
        $this->assertSame(1, count($paginas[0]));
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $fila = fn (int $dia, string $fecha, bool $cerrada): array => [
            'numero' => $dia,
            'fecha_completa' => $fecha,
            'ruta' => 'Bodega - Cliente',
            'ruta_unica' => 'Bodega - Cliente',
            'detalle_cierre' => 'Funcionario CC CC 1 - Ana Pérez',
            'hora_inicio' => '06:00',
            'descanso_inicio' => '12:00',
            'descanso_fin' => '13:00',
            'hora_fin' => '18:00',
            'total_hours' => '11:00',
            'km_inicial' => '10000',
            'km_final' => '10300',
            'km_total' => '300',
            'is_closed' => $cerrada,
            'firma_funcionario' => null,
            'conductor' => 'Carlos Pérez',
        ];

        return [
            'logo' => null,
            'logo_mime' => 'image/png',
            'letterhead' => null,
            'logo_fondo' => null,
            'ocultar_marca' => false,
            'empresa_transportadora' => 'TRANSPORTES DE PRUEBA S.A.S.',
            'periodo' => 'octubre 2026',
            'es_historial' => false,
            'placa' => 'ABC123',
            'numero_interno' => 'M-001',
            'area' => 'Ana Pérez',
            'clase' => 'MICROBÚS',
            'nit' => '900.000.000-1',
            'empresa' => 'CLIENTE DE PRUEBA S.A.S.',
            'dias' => [
                $fila(20, '20/10/2026', true),
                $fila(21, '21/10/2026', false),
            ],
            'totales' => [
                'dias' => 2,
                'dias_cerrados' => 1,
                'horas_minutos' => 19 * 60 + 30,
                'horas' => '19:30',
                'km' => 450.0,
            ],
            'observaciones' => 'Recorridos del día: 2',
            'firma_conductor' => null,
            'firma_recibido' => null,
            'proyecto' => 'PROYECTO DE PRUEBA',
            'proyecto_vigencia' => '01/01/2026 - 31/12/2026',
            'evidencia_incompleta' => false,
            'firmas_pendientes' => [],
            'certificaciones_pendientes' => [],
            'dias_con_excepcion' => [],
        ];
    }
}
