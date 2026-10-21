<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ThirdParty;
use App\Services\Pdf\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * La ficha técnica del conductor toma el logo de la empresa de la colección
 * `LOGO` (la que registra `HasFiles`). Antes el código buscaba `logos`, por lo
 * que el encabezado y la marca de agua se quedaban sin imagen (el respaldo por
 * el accesor `logo` lo tapaba en casi todos los PDF, pero no en el membrete).
 */
class FichaTecnicaConductorLogoTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private Company $empresa;

    private PdfService $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);

        $companyUuid = $this->insertar('companies', [
            'business_name' => 'TRANSPORTES DE PRUEBA S.A.S.',
            'document_number' => '901555001',
            'is_active' => true,
        ]);

        $this->empresa = Company::withoutGlobalScopes()->where('uuid', $companyUuid)->firstOrFail();
        $this->empresa->addFile(UploadedFile::fake()->image('logo-empresa.png', 120, 60), 'LOGO');

        $this->pdf = app(PdfService::class);
    }

    public function test_el_logo_se_resuelve_desde_la_coleccion_logo(): void
    {
        $logo = PdfService::logoParaMarca($this->empresa);

        $this->assertNotNull($logo, 'El logo de la empresa debe resolverse');
        $this->assertSame('LOGO', $logo->collection_name);

        $base64 = $this->pdf->getBase64Parallel(['logo' => $logo]);
        $this->assertNotEmpty($base64['logo'], 'El logo debe codificarse para incrustarlo en el PDF');
    }

    public function test_el_membrete_cae_al_logo_de_la_empresa(): void
    {
        $membrete = $this->pdf->letterheadDataUrl($this->empresa);

        $this->assertNotNull($membrete, 'Sin hoja membretada el membrete usa el logo de la empresa');
        $this->assertStringStartsWith('data:image/', $membrete);
    }

    public function test_la_ficha_del_conductor_pinta_el_logo_en_encabezado_y_fondo(): void
    {
        $driverUuid = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa->uuid,
            'document_number' => '1020304050',
            'first_name' => 'LUIS RAFAEL',
            'last_name' => 'OVIEDO ROMERO',
            'phone' => '3001234567',
            'email' => 'conductor@example.test',
            'is_driver' => true,
            'is_active' => true,
        ]);

        $driver = ThirdParty::withoutGlobalScopes()
            ->with(['company', 'socialSecurityContributions'])
            ->where('uuid', $driverUuid)
            ->firstOrFail();

        $logo = PdfService::logoParaMarca($driver->company);
        $images = $this->pdf->getBase64Parallel(['logo' => $logo, 'photo' => null]);

        $html = view('pdf.third-parties.technical-sheet', [
            'driver' => $driver,
            'data' => $images,
            'letterhead' => null,
            'logo_fondo' => $images['logo'],
            'ocultar_marca' => false,
        ])->render();

        $uri = 'data:image/png;base64,'.$images['logo'];

        $this->assertSame(2, substr_count($html, $uri), 'El logo va en el encabezado y en la marca de agua');
        $this->assertStringContainsString('id="watermark"', $html);
    }
}
