<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pdf\PdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * Base estándar de impresión PDF (parciales de `pdf.standard.*`).
 */
class PdfBaseEstandarTest extends TestCase
{
    public function test_siglas_de_empresa_son_dinamicas_y_omiten_sufijos_legales(): void
    {
        $this->assertSame('TES', PdfService::siglasEmpresa('TRANSPORTES ESPECIALES SIN BARRERAS S.A.S.'));
        $this->assertSame('ACM', PdfService::siglasEmpresa('Acme Corp Mundial Ltda'));
        $this->assertSame('EMP', PdfService::siglasEmpresa(''));
    }

    public function test_estilos_respetan_orientacion_y_margen_sin_reset_universal_de_margen(): void
    {
        $horizontal = view('pdf.standard.styles', ['orientation' => 'landscape'])->render();
        $this->assertStringContainsString('size: letter landscape', $horizontal);
        $this->assertStringContainsString('margin: 8mm 10mm 8mm 8mm', $horizontal);
        $this->assertDoesNotMatchRegularExpression('/\*\s*\{[^}]*margin\s*:/s', $horizontal);

        $carta = view('pdf.standard.styles', ['pdfMargen' => 'cartas'])->render();
        $this->assertStringContainsString('size: letter portrait', $carta);
        $this->assertStringContainsString('margin: 18mm 20mm 18mm 20mm', $carta);

        foreach (['display: flex', 'display: grid', 'box-sizing', 'box-shadow'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $horizontal);
        }
    }

    public function test_encabezado_usa_logo_de_la_empresa_o_monograma_con_sus_siglas(): void
    {
        $base = [
            'empresa_transportadora' => 'OTRA EMPRESA S.A.S.',
            'titulo_documento' => 'DOCUMENTO DE PRUEBA',
            'meta_sgc' => ['CÓDIGO' => 'XX-F-001', 'VERSIÓN' => '001'],
        ];

        $conLogo = view('pdf.standard.header', $base + [
            'logo_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
            'empresa_siglas' => 'OE',
        ])->render();
        $this->assertStringContainsString('class="header-logo-img"', $conLogo);
        $this->assertStringNotContainsString('logo-monogram', $conLogo);

        $sinLogo = view('pdf.standard.header', $base + ['logo_data_url' => null, 'empresa_siglas' => 'OE'])->render();
        $this->assertStringNotContainsString('header-logo-img', $sinLogo);
        $this->assertStringContainsString('>OE<', $sinLogo);
        $this->assertStringContainsString('XX-F-001', $sinLogo);
        // Nada de la identidad de otra empresa queda quemado en la plantilla.
        $this->assertStringNotContainsString('SIN BARRERAS', $sinLogo);
    }

    public function test_linea_de_vehiculo_omite_el_separador_sin_numero_interno(): void
    {
        $con = view('pdf.standard.vehicle-line', ['placa' => 'ABC123', 'numero_interno' => '42'])->render();
        $this->assertStringContainsString('&middot;', $con);

        $sin = view('pdf.standard.vehicle-line', ['placa' => 'ABC123'])->render();
        $this->assertStringNotContainsString('&middot;', $sin);
    }

    public function test_documento_completo_con_la_base_renderiza_en_carta_sin_avisos(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . view('pdf.standard.styles', ['orientation' => 'portrait'])->render()
            . '</head><body>'
            . view('pdf.standard.header', [
                'logo_data_url' => null,
                'empresa_siglas' => 'EMP',
                'empresa_transportadora' => 'EMPRESA DE PRUEBA',
                'titulo_documento' => 'PRUEBA',
                'meta_sgc' => ['CÓDIGO' => 'XX-F-001'],
            ])->render()
            . view('pdf.standard.vehicle-line', ['placa' => 'ABC123', 'numero_interno' => '1'])->render()
            . '</body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'portrait')->setWarnings(true);
        $salida = $pdf->output();

        preg_match('~/MediaBox\s*\[([^\]]+)\]~', $salida, $caja);
        $this->assertSame('0.000 0.000 612.000 792.000', preg_replace('/\s+/', ' ', trim($caja[1] ?? '')));
        preg_match_all('~/Type\s*/Page([^s]|$)~', $salida, $paginas);
        $this->assertSame(1, count($paginas[0]));
    }
}
