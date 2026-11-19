<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Services\Pdf\PdfService;
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

    public function test_la_placa_y_el_interno_usan_la_micro_tabla_alineada_de_la_base_estandar(): void
    {
        $html = view('pdf.maintenance.service-control-sheet', $this->datos())->render();

        $this->assertStringContainsString('class="placa-inline-tbl"', $html);
        $this->assertStringContainsString('class="placa-badge">ABC123', $html);
        $this->assertStringContainsString('class="vehiculo-sep"', $html);
        $this->assertStringContainsString('class="vehiculo-interno">M-001', $html);
        // El inline-block con borde desalineaba la etiqueta respecto al interno en DomPDF.
        $this->assertStringNotContainsString('display: inline-block;
        }

        .highlight-client', $html);
    }

    public function test_la_clase_del_vehiculo_sale_de_la_descripcion_y_no_de_un_name_inexistente(): void
    {
        $servicio = app(PdfService::class);
        $claseDeHoja = new \ReflectionMethod($servicio, 'claseDeHoja');
        $claseDeHoja->setAccessible(true);

        $vehiculo = (object) ['vehicleClass' => (object) ['description' => 'MICROBÚS']];
        $directa = new ServiceDeliveryControlSheet(['type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA']);
        $directa->setRelation('internalControl', (object) ['vehicle' => $vehiculo]);
        $this->assertSame('MICROBÚS', $claseDeHoja->invoke($servicio, $directa));

        $subcontratada = new ServiceDeliveryControlSheet(['type_of_control_sheet' => 'SUBCONTRATADO']);
        $subcontratada->setRelation('subcontractedControl', (object) ['vehicleClass' => (object) ['description' => 'BUSETA']]);
        $this->assertSame('BUSETA', $claseDeHoja->invoke($servicio, $subcontratada));

        $sinClase = new ServiceDeliveryControlSheet(['type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA']);
        $this->assertSame('N/A', $claseDeHoja->invoke($servicio, $sinClase));
    }

    public function test_la_leyenda_lateral_queda_centrada_entre_la_tabla_y_el_borde_de_la_hoja(): void
    {
        $pdf = Pdf::loadView('pdf.maintenance.service-control-sheet', $this->datos())->setPaper('letter', 'landscape');
        app(PdfService::class)->sellarPaginado($pdf, PdfService::MARGEN_DERECHO_PLANILLA_PT);
        $contenido = $pdf->output();

        $x = null;
        preg_match_all('/stream\r?\n(.*?)endstream/s', $contenido, $streams);
        foreach ($streams[1] as $crudo) {
            $datos = @gzuncompress(trim($crudo, "\r\n"));
            if ($datos && preg_match('/0\.000 1\.000 -1\.000 0\.000 ([\d.]+) [\d.]+ Tm/', $datos, $m)) {
                $x = (float) $m[1];
                break;
            }
        }
        $this->assertNotNull($x, 'No se encontró la leyenda rotada en el PDF.');

        $ancho = 792.0;
        $tamano = 7.5;
        $bordeTabla = $ancho - PdfService::MARGEN_DERECHO_PLANILLA_PT;
        $izquierdaGlifo = $x - $tamano * 0.72;
        $derechaGlifo = $x + $tamano * 0.22;

        $holguraConTabla = $izquierdaGlifo - $bordeTabla;
        $holguraConBorde = $ancho - $derechaGlifo;

        $this->assertGreaterThan(5.0, $holguraConTabla, 'La leyenda no debe pegarse a la tabla.');
        $this->assertEqualsWithDelta($holguraConTabla, $holguraConBorde, 0.5, 'La leyenda debe quedar centrada en la franja.');
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

    public function test_adversarial_planilla_sin_logo_usa_siglas_dinamicas_y_esquema_neutro_sin_quemar_otra_empresa(): void
    {
        $datos = $this->datos();
        $datos['logo'] = null;
        $datos['empresa_transportadora'] = 'LOGÍSTICA DEL CARIBE S.A.S.';
        $datos['empresa_siglas'] = PdfService::siglasEmpresa($datos['empresa_transportadora']);

        $html = view('pdf.maintenance.service-control-sheet', $datos)->render();

        $this->assertStringContainsString('>LDC<', preg_replace('/\s+/', '', $html) ?? '');
        $this->assertStringContainsString("font-family: 'Inter'", $html);
        $this->assertStringContainsString('#d8d8d8', $html);
        $this->assertStringContainsString('#f0f0f0', $html);
        $this->assertStringNotContainsString('Transportes Sin Barreras', $html);
        $this->assertStringNotContainsString('>SB<', $html);
        $this->assertStringNotContainsString('#1e3a8a', $html);
        $this->assertStringNotContainsString("'Courier'", $html);
    }

    public function test_adversarial_resolve_logo_with_mime_nulo_no_filtra_logos_de_disco(): void
    {
        $servicio = app(PdfService::class);
        $metodo = new \ReflectionMethod($servicio, 'resolveLogoWithMime');
        $metodo->setAccessible(true);

        $resultado = $metodo->invoke($servicio, null, null);

        $this->assertSame(['logo' => null, 'mime' => 'image/png'], $resultado);
    }

    public function test_planilla_oculta_celda_logo_en_modos_membrete_y_limpio_y_soporta_hoja_horizontal(): void
    {
        $pngTiny = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $imgHorizontal = imagecreatetruecolor(140, 100);
        ob_start();
        imagepng($imgHorizontal);
        $hojaHorizontal = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
        imagedestroy($imgHorizontal);

        $datos = $this->datos();
        $datos['logo'] = $pngTiny;

        $conLogo = view('pdf.maintenance.service-control-sheet', array_merge($datos, ['ocultar_marca' => false]))->render();
        $this->assertStringContainsString('class="header-logo-cell"', $conLogo);
        $this->assertStringContainsString('alt="Logo empresa"', $conLogo);
        foreach (['#dcfce7', '#fef3c7', '#fef08a', '#fffbeb', '#fef2f2', '#f0f9ff'] as $colorLegado) {
            $this->assertStringNotContainsString($colorLegado, $conLogo);
        }

        $limpio = view('pdf.maintenance.service-control-sheet', array_merge($datos, ['ocultar_marca' => true]))->render();
        $this->assertStringNotContainsString('class="header-logo-cell"', $limpio);
        $this->assertStringNotContainsString('alt="Logo empresa"', $limpio);

        $conHoja = view('pdf.maintenance.service-control-sheet', array_merge($datos, ['letterhead' => $hojaHorizontal]))->render();
        $this->assertStringContainsString('id="letterhead-page-bg"', $conHoja);
        $this->assertStringNotContainsString('class="header-logo-cell"', $conHoja);
    }
}
