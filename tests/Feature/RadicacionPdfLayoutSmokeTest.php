<?php

declare(strict_types=1);

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RadicacionPdfLayoutSmokeTest extends TestCase
{
    private const PNG_1X1_RAW = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private const PNG_1X1 = 'data:image/png;base64,' . self::PNG_1X1_RAW;

    private const PNG_HOJA_VERTICAL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAACMCAIAAAAFl5vsAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAQElEQVR4nO3BMQEAAADCoPVPbQo/oAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAhwGknAABAZ3bTQAAAABJRU5ErkJggg==';

    /**
     * @return array<string, array{0: string}>
     */
    public static function vistasRadicacionProvider(): array
    {
        return [
            'carta_capacidad' => ['pdf.radicacion.capacity-letter'],
            'carta_aceptacion' => ['pdf.radicacion.acceptance-letter'],
            'inclusion_polizas' => ['pdf.radicacion.policy-inclusion-letter'],
            'renovacion_tarjeta' => ['pdf.radicacion.renewal-request'],
            'acta_desvinculacion' => ['pdf.radicacion.unlink-record'],
            'pagare' => ['pdf.radicacion.promissory-note'],
            'contrato_vinculacion' => ['pdf.radicacion.affiliation-contract'],
            'contrato_prestacion' => ['pdf.radicacion.service-provision-contract'],
        ];
    }

    #[DataProvider('vistasRadicacionProvider')]
    public function test_vistas_radicacion_usan_estandar_neutro_y_modos_de_marca(string $vista): void
    {
        $datos = $this->datosBase();

        // 1. Modo fondo: logo en cabecera y marca de agua (base64 crudo, contrato real de PdfService::conMarca)
        $htmlFondo = view($vista, array_merge($datos, [
            'letterhead' => null,
            'logo_fondo' => self::PNG_1X1_RAW,
            'ocultar_marca' => false,
            'es_hoja_membretada' => false,
        ]))->render();

        $this->assertStringContainsString('Inter-Regular.ttf', $htmlFondo);
        $this->assertStringContainsString('--pdf-banda: #d8d8d8', $htmlFondo);
        $this->assertStringContainsString('--pdf-encabezado: #f0f0f0', $htmlFondo);
        $this->assertStringContainsString('id="watermark"', $htmlFondo);
        $this->assertStringContainsString('src="data:image/png;base64,' . self::PNG_1X1_RAW . '"', $htmlFondo);
        $this->assertStringContainsString('alt="Logo de la empresa"', $htmlFondo);
        $this->assertStringNotContainsString('id="letterhead-page-bg"', $htmlFondo);

        $bodyFondo = Str::after($htmlFondo, '<body');
        $this->assertStringNotContainsString('#1d4ed8', $htmlFondo);
        $this->assertStringNotContainsString('#1e40af', $htmlFondo);
        $this->assertStringNotContainsString('#d1fae5', $htmlFondo);
        $this->assertStringNotContainsString('Courier New', $htmlFondo);
        $this->assertStringNotContainsString('Arial, Helvetica', $bodyFondo);

        // 2. Modo membrete: fondo completo y sin duplicar logo de empresa
        $htmlMembrete = view($vista, array_merge($datos, [
            'letterhead' => self::PNG_HOJA_VERTICAL,
            'logo_fondo' => null,
            'ocultar_marca' => false,
            'es_hoja_membretada' => true,
        ]))->render();

        $this->assertStringContainsString('id="letterhead-page-bg"', $htmlMembrete);
        $this->assertStringNotContainsString('id="watermark"', $htmlMembrete);
        $this->assertStringNotContainsString('alt="Logo de la empresa"', $htmlMembrete);

        // 3. Modo limpio: sin membrete, sin watermark y sin logo de empresa
        $htmlLimpio = view($vista, array_merge($datos, [
            'letterhead' => null,
            'logo_fondo' => null,
            'ocultar_marca' => true,
            'es_hoja_membretada' => false,
        ]))->render();

        $this->assertStringNotContainsString('id="letterhead-page-bg"', $htmlLimpio);
        $this->assertStringNotContainsString('id="watermark"', $htmlLimpio);
        $this->assertStringNotContainsString('alt="Logo de la empresa"', $htmlLimpio);

        $this->assertDomPdfLimpio($htmlFondo);
        $this->assertDomPdfLimpio($htmlMembrete);
    }

    public function test_carta_capacidad_con_membrete_cabe_en_una_pagina(): void
    {
        $html = view('pdf.radicacion.capacity-letter', array_merge($this->datosBase(), [
            'letterhead' => self::PNG_HOJA_VERTICAL,
            'logo_fondo' => null,
            'ocultar_marca' => false,
            'es_hoja_membretada' => true,
        ]))->render();

        $bytes = $this->assertDomPdfLimpio($html);
        preg_match_all('~/Type\s*/Page([^s]|$)~', $bytes, $coincidencias);
        $this->assertCount(1, $coincidencias[0], 'La carta de capacidad debe caber en 1 página.');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosBase(): array
    {
        return [
            'expediente' => [
                'codigo' => 'RAD-2026-0042',
                'tipo_tramite' => 'CAMBIO_DE_EMPRESA',
                'fecha_radicacion' => '19/11/2026',
                'asunto' => 'Vinculación al parque automotor',
                'ciudad' => 'Bogotá D.C.',
                'paso' => 'CAPACIDAD_TRANSPORTADORA',
                'estado_paso' => 'EN_TRAMITE',
                'estado_global' => 'EN_PROCESO',
                'empresa_origen' => 'TRANSPORTADORA DEMO S.A.S.',
                'paz_y_salvo' => 'PS-2026-118',
                'tarjeta_anterior' => 'TO-884210',
                'radicado' => '20263050192841',
                'resolucion' => 'RES-00412-2026',
                'soat_valid' => true,
                'rtm_valid' => true,
                'simit_clear' => true,
            ],
            'empresa' => [
                'razon_social' => 'TRANSPORTES DEMO S.A.S.',
                'nombre_comercial' => 'DEMO',
                'nit' => '900.123.456-7',
                'representante' => 'CARLOS MENDOZA',
                'documento_representante' => '79.845.120',
                'direccion' => 'Cra. 45 # 26-85',
                'telefono' => '(601) 456 7890',
                'correo' => 'gerencia@demo.test',
                'ciudad' => 'Bogotá D.C.',
                'firma_representante' => ['imagen' => null, 'texto' => 'Carlos Mendoza'],
            ],
            'destinatario' => [
                'dependencia' => 'Dirección Territorial Cundinamarca',
                'entidad' => 'Ministerio de Transporte',
                'director' => 'Dra. María Gómez',
            ],
            'vehiculo' => [
                'placa' => 'GUX649',
                'marca' => 'CHEVROLET',
                'linea' => 'NPR REWARD',
                'modelo' => '2024',
                'vin' => '9GDNPR71LRA001234',
                'numero_chasis' => '9GDNPR71LRA001234',
                'numero_motor' => '4HK1-892341',
                'clase' => 'BUSETA',
                'carroceria' => 'CERRADA',
                'tarjeta_operacion' => 'TO-991042',
                'pasajeros' => 24,
                'carga' => null,
            ],
            'propietario' => [
                'nombre' => 'JUAN CARLOS PÉREZ',
                'documento' => '79.123.456',
                'es_propietario' => true,
                'firma' => ['imagen' => null, 'texto' => 'Juan Carlos Pérez'],
            ],
            'polizas' => [
                'rcc_numero' => 'RCC-90123456',
                'rcc_aseguradora' => 'Seguros Bolívar S.A.',
                'rce_numero' => 'RCE-90123457',
                'rce_aseguradora' => 'Seguros Bolívar S.A.',
            ],
            'vinculacion' => [
                'numero' => 'CV-2026-0091',
                'fecha_emision' => '19/11/2026',
                'fecha_inicio' => '19/11/2026',
                'fecha_fin' => '19/11/2027',
                'firma_propietario' => ['imagen' => null, 'texto' => 'Juan Carlos Pérez'],
                'firma_empresa' => ['imagen' => null, 'texto' => 'Carlos Mendoza'],
            ],
            'prestacion' => [
                'numero' => 'CPS-2026-0044',
                'fecha_emision' => '19/11/2026',
                'fecha_inicio' => '19/11/2026',
                'fecha_fin' => '19/11/2027',
                'cobertura' => 'Nacional',
            ],
            'imagenes' => [
                'ministerio' => self::PNG_1X1,
                'superintendencia' => null,
                'empresa' => self::PNG_1X1,
            ],
        ];
    }

    private function assertDomPdfLimpio(string $html): string
    {
        global $_dompdf_warnings;
        $_dompdf_warnings = [];

        $bytes = Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait')
            ->setOption(['dpi' => 120, 'isFontSubsettingEnabled' => true])
            ->output();

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame([], $_dompdf_warnings ?? [], 'DomPDF emitió avisos al renderizar');

        return $bytes;
    }
}
