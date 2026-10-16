<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SystemConfiguration;
use App\Services\Pdf\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Presentación por documento PDF: cada empresa define en
 * `system_configuration.pdf_branding` si un PDF lleva hoja membretada,
 * logo de fondo o nada. Sin configurar, el defecto es logo de fondo.
 */
class PdfBrandingTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    public function test_sin_configurar_el_modo_defecto_es_fondo(): void
    {
        $empresa = $this->insertar('companies', ['business_name' => 'Empresa branding', 'document_number' => '901797713', 'is_active' => true]);
        $this->insertar('system_configuration', ['company_uuid' => $empresa]);

        $servicio = app(PdfService::class);

        $this->assertSame(PdfService::PDF_MODO_FONDO, $servicio->modoPdfPara($empresa, 'mantenimiento'));
        $this->assertSame(PdfService::PDF_MODO_FONDO, $servicio->modoPdfPara($empresa, 'clave_inexistente'));
        $this->assertSame(PdfService::PDF_MODO_FONDO, $servicio->modoPdfPara(null, 'fuec'));
    }

    public function test_el_modo_configurado_se_respeta_y_lo_invalido_cae_al_defecto(): void
    {
        $empresa = $this->insertar('companies', ['business_name' => 'Empresa branding 2', 'document_number' => '901797714', 'is_active' => true]);
        $uuid = $this->insertar('system_configuration', [
            'company_uuid' => $empresa,
            'pdf_branding' => json_encode(['mantenimiento' => 'membrete', 'fuec' => 'limpio', 'planilla_diaria' => 'otro']),
        ]);
        $this->assertNotEmpty($uuid);

        $servicio = app(PdfService::class);

        $this->assertSame('membrete', $servicio->modoPdfPara($empresa, 'mantenimiento'));
        $this->assertSame('limpio', $servicio->modoPdfPara($empresa, 'fuec'));
        $this->assertSame(PdfService::PDF_MODO_FONDO, $servicio->modoPdfPara($empresa, 'planilla_diaria'));
    }

    public function test_con_marca_inyecta_las_claves_en_los_datos_de_la_vista(): void
    {
        $empresa = $this->insertar('companies', ['business_name' => 'Empresa branding 3', 'document_number' => '901797715', 'is_active' => true]);
        $this->insertar('system_configuration', [
            'company_uuid' => $empresa,
            'pdf_branding' => json_encode(['mantenimiento' => 'limpio']),
        ]);
        $config = SystemConfiguration::withoutGlobalScopes()->where('company_uuid', $empresa)->firstOrFail();

        $servicio = app(PdfService::class);
        $datos = $servicio->conMarca(['placa' => 'ABC123'], $config->company, 'mantenimiento', null);

        $this->assertTrue($datos['ocultar_marca']);
        $this->assertNull($datos['letterhead']);
        $this->assertNull($datos['logo_fondo']);
        $this->assertSame('ABC123', $datos['placa']);
    }
}
