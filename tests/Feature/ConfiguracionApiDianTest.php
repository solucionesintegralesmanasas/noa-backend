<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingProvider;
use App\Services\Billing\ApiDianConfiguracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Módulo 01 - Configuraciones Básicas de ApiDian.
 *
 * Todo el transporte se prueba con Http::fake(): el servicio solo hace de
 * proxy autenticado de los pasos de configuración de la cuenta.
 */
class ConfiguracionApiDianTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private const BASE = 'https://apidian-sandbox.test';

    private const NIT = '8901928119';

    private BillingProvider $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $empresa = $this->insertar('companies', [
            'business_name' => 'Config ApiDian Test',
            'document_number' => self::NIT,
        ]);

        $uuid = $this->insertar('billing_providers', [
            'company_uuid' => $empresa,
            'name' => 'ApiDian',
            'driver' => 'apidian',
            'server_url' => self::BASE,
            'client_secret' => Crypt::encryptString('token_de_prueba'),
            'is_active' => 1,
        ]);

        $this->proveedor = BillingProvider::where('uuid', $uuid)->firstOrFail();
    }

    private function configuracion(): ApiDianConfiguracion
    {
        return new ApiDianConfiguracion($this->proveedor);
    }

    public function test_consulta_la_empresa_con_el_nit_y_el_token(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/'.self::NIT => Http::response([
                'success' => true,
                'data' => ['name' => 'SANDBOX PRUEBAS', 'identification_number' => 8901928119],
            ], 200),
        ]);

        $respuesta = $this->configuracion()->consultarEmpresa();

        $this->assertSame('SANDBOX PRUEBAS', $respuesta['data']['name']);
        Http::assertSent(fn ($peticion) => $peticion->method() === 'GET'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/'.self::NIT
            && $peticion->hasHeader('Authorization', 'Bearer token_de_prueba'));
    }

    public function test_guarda_la_empresa_del_paso_1(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/'.self::NIT => Http::response(['success' => true], 200),
        ]);

        $datos = [
            'identification_number' => 8901928119,
            'dv' => '7',
            'name' => 'SANDBOX PRUEBAS SAS',
            'type_document_identification_id' => 6,
            'type_organization_id' => 1,
            'type_liability_id' => 7,
            'type_regime_id' => 2,
            'type_environment_id' => 2,
            'municipality_id' => 149,
        ];

        $this->configuracion()->guardarEmpresa($datos);

        Http::assertSent(fn ($peticion) => $peticion->method() === 'POST'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/'.self::NIT
            && $peticion['identification_number'] === 8901928119
            && $peticion['type_environment_id'] === 2);
    }

    public function test_configura_software_y_software_de_nomina(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/software' => Http::response(['success' => true], 200),
            self::BASE.'/api/ubl2.1/config/softwarepayroll' => Http::response(['success' => true], 200),
        ]);

        $this->configuracion()->guardarSoftware(['id' => 'GUID-SOFTWARE', 'pin' => '12345', 'url' => '']);
        $this->configuracion()->guardarSoftwareNomina(['idpayroll' => 'GUID-NOMINA', 'pinpayroll' => '54321', 'send' => false]);

        Http::assertSent(fn ($peticion) => $peticion->method() === 'PUT'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/software'
            && $peticion['pin'] === '12345');
        Http::assertSent(fn ($peticion) => $peticion->url() === self::BASE.'/api/ubl2.1/config/softwarepayroll'
            && $peticion['idpayroll'] === 'GUID-NOMINA'
            && $peticion['send'] === false);
    }

    public function test_carga_el_certificado_en_base64(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/certificate' => Http::response(['success' => true], 200),
        ]);

        $this->configuracion()->guardarCertificado([
            'certificate' => base64_encode('contenido-p12'),
            'password' => 'clave-certificado',
        ]);

        Http::assertSent(fn ($peticion) => $peticion->method() === 'PUT'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/certificate'
            && $peticion['certificate'] === base64_encode('contenido-p12')
            && $peticion['password'] === 'clave-certificado');
    }

    public function test_configura_resolucion_y_ambiente(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/resolution' => Http::response(['success' => true], 200),
            self::BASE.'/api/ubl2.1/config/environment' => Http::response(['success' => true], 200),
        ]);

        $this->configuracion()->guardarResolucion([
            'type_document_id' => 1,
            'prefix' => 'SETP',
            'resolution' => '18760000001',
            'from_number' => 980000000,
            'to_number' => 995000000,
            'date_from' => '2025-01-19',
            'date_to' => '2030-01-19',
        ]);
        $this->configuracion()->cambiarAmbiente([
            'type_environment_id' => 2,
            'payroll_type_environment_id' => 2,
            'eqdocs_type_environment_id' => 2,
            'test_set_id' => '',
        ]);

        Http::assertSent(fn ($peticion) => $peticion->method() === 'POST'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/resolution'
            && $peticion['prefix'] === 'SETP'
            && $peticion['to_number'] === 995000000);
        Http::assertSent(fn ($peticion) => $peticion->method() === 'PUT'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/environment'
            && $peticion['type_environment_id'] === 2);
    }

    public function test_configura_webhook_y_elimina_la_empresa(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/webhook' => Http::response(['success' => true], 200),
            self::BASE.'/api/ubl2.1/config/'.self::NIT => Http::response(['success' => true], 200),
        ]);

        $this->configuracion()->guardarWebhook([
            'webhook_url' => 'https://miapp.test/webhook/apidian',
            'webhook_secret' => 'secreto',
        ]);
        $this->configuracion()->eliminarEmpresa();

        Http::assertSent(fn ($peticion) => $peticion->method() === 'PUT'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/webhook'
            && $peticion['webhook_secret'] === 'secreto');
        Http::assertSent(fn ($peticion) => $peticion->method() === 'DELETE'
            && $peticion->url() === self::BASE.'/api/ubl2.1/config/'.self::NIT);
    }

    public function test_error_de_apidian_se_propaga_con_su_mensaje(): void
    {
        Http::fake([
            self::BASE.'/api/ubl2.1/config/resolution' => Http::response([
                'success' => false,
                'message' => 'La resolución ya existe para ese prefijo.',
            ], 422),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La resolución ya existe para ese prefijo.');
        $this->configuracion()->guardarResolucion(['prefix' => 'SETP']);
    }

    public function test_un_proveedor_sin_apidian_no_tiene_configuracion(): void
    {
        $this->proveedor->update(['driver' => 'nulo']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no usa ApiDian');
        new ApiDianConfiguracion($this->proveedor->fresh());
    }
}
