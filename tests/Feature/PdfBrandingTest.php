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
        $this->assertContains('nota_credito', PdfService::PDF_CLAVES);
        $this->assertContains('nota_debito', PdfService::PDF_CLAVES);
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

    public function test_con_marca_ejecuta_cero_consultas_cuando_la_empresa_viene_precargada(): void
    {
        $empresaUuid = $this->insertar('companies', ['business_name' => 'Empresa precargada', 'document_number' => '901797716', 'is_active' => true]);
        $this->insertar('system_configuration', [
            'company_uuid' => $empresaUuid,
            'pdf_branding' => json_encode(['reporte_vehiculos' => 'limpio', 'reporte_actividad_vehicular' => 'membrete']),
        ]);

        $company = \App\Models\Company::with(['media', 'systemConfiguration.media'])
            ->where('uuid', $empresaUuid)
            ->firstOrFail();

        $servicio = app(PdfService::class);
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $limpio = $servicio->conMarca(['filas' => []], $company, 'reporte_vehiculos', null);
        $membrete = $servicio->conMarca(['filas' => []], $company, 'reporte_actividad_vehicular', null);

        $consultas = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertCount(0, $consultas, 'conMarca no debe consultar BD cuando Company trae systemConfiguration.media precargada');
        $this->assertTrue($limpio['ocultar_marca']);
        $this->assertFalse($membrete['ocultar_marca']);
    }
}
