<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * SPEC-006: un adjunto cuyo archivo físico no existe degrada a URL vacía en la
 * serialización del modelo (nunca a null ni a una URL rota).
 */
class SerializacionArchivosTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    private function empresaConLogo(): Company
    {
        $uuid = $this->insertar('companies', [
            'business_name' => 'Empresa con logo',
            'document_number' => '901797712',
            'is_active' => true,
        ]);
        $company = Company::withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
        $company->addFile(UploadedFile::fake()->image('logo.png'), 'LOGO');

        return $company->fresh();
    }

    public function test_un_logo_existente_expone_su_url(): void
    {
        $company = $this->empresaConLogo();
        $company->load('media');

        $array = $company->toArray();

        $this->assertStringContainsString('LOGO', (string) $array['logo_url']);
        $this->assertSame($array['logo_url'], $array['media'][0]['url']);
    }

    public function test_un_archivo_borrado_del_disco_degrada_a_url_vacia(): void
    {
        $company = $this->empresaConLogo();
        $company->load('media');
        Storage::disk('public')->deleteDirectory('companies');

        $array = $company->toArray();

        $this->assertSame('', $array['logo_url'], 'El contrato degrada a cadena vacía, no a null');
        $this->assertSame('', $array['media'][0]['url']);
    }
}
