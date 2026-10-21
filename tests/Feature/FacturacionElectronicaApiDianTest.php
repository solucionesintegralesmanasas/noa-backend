<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use App\Services\Billing\ContratoFacturacionElectronica;
use App\Services\Billing\ContratoNotasElectronica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Fases 1 y 2 del proveedor tecnológico: driver ApiDian alojado por empresa.
 *
 * Regla del proveedor (colección Postman de referencia): send=false valida,
 * firma y persiste SIN contactar la DIAN; las emisiones con test_set_id van a
 * la ruta de habilitación. Todo el transporte se prueba con Http::fake().
 */
class FacturacionElectronicaApiDianTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $cliente;

    private string $resolucion;

    private const BASE = 'https://apidian-sandbox.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'ApiDian Test']);
        $this->cliente = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'JURIDICA',
            'document_number' => '900226715',
            'nit_check_digit' => '3',
            'company_name' => 'CLIENTE DE PRUEBA SAS',
            'email' => 'cliente@apidian.test',
            'address' => 'CLL 1 # 1-1',
            'phone' => '3001234567',
        ]);
        $this->resolucion = $this->insertar('billing_resolutions', [
            'company_uuid' => $this->empresa,
            'resolution_type_code' => '01',
            'resolution_number' => '18760000001',
            'invoice_prefix' => 'TSB',
            'invoice_start_number' => 1,
            'invoice_end_number' => 9999,
            'last_consecutive' => 0,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'is_active' => 1,
        ]);
    }

    private function proveedor(array $datos = []): string
    {
        return $this->insertar('billing_providers', $datos + [
            'company_uuid' => $this->empresa,
            'name' => 'ApiDian',
            'driver' => 'apidian',
            'server_url' => self::BASE,
            'test_set_id' => 'set123',
            'send_to_dian' => 0,
            'client_id' => 'espor566731',
            'client_secret' => \Illuminate\Support\Facades\Crypt::encryptString('token_de_prueba'),
            'is_active' => 1,
        ]);
    }

    private function factura(array $datos = []): CommercialInvoice
    {
        $uuidFactura = $this->insertar('commercial_invoices', $datos + [
            'company_uuid' => $this->empresa,
            'invoice_type' => 'FACTURA_VENTA',
            'status' => 'EMITIDA_INTERNA',
            'invoice_number' => 'TSB-00000001',
            'issue_date' => '2026-10-05',
            'due_date' => '2026-11-04',
            'third_party_uuid' => $this->cliente,
            'billing_resolution_uuid' => $this->resolucion,
            'subtotal' => 100000,
            'total_discount' => 0,
            'total_tax' => 19000,
            'total_withholding' => 0,
            'invoice_total' => 119000,
            'electronic_status' => 'PENDIENTE_DIAN',
        ]);

        $this->insertar('commercial_invoice_items', [
            'invoice_uuid' => $uuidFactura,
            'order_index' => 1,
            'description' => 'Servicio de transporte especial',
            'quantity' => 1,
            'unit_price' => 100000,
            'tax_rate' => 19,
            'tax_amount' => 19000,
            'line_total' => 119000,
        ]);

        return CommercialInvoice::where('uuid', $uuidFactura)->firstOrFail();
    }

    private function puertoFacturas(): ContratoFacturacionElectronica
    {
        return app(ContratoFacturacionElectronica::class);
    }

    private function puertoNotas(): ContratoNotasElectronica
    {
        return app(ContratoNotasElectronica::class);
    }

    public function test_emite_factura_sin_transmision_usa_test_set_y_guarda_cufe(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response([
                'success' => true,
                'data' => ['cufe' => 'cufe-sandbox', 'zipkey' => 'zip-1', 'xml' => 'PD94bWw=', 'IsValid' => true],
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cufe-sandbox', $respuesta['cufe']);

        $factura->refresh();
        $this->assertSame('VALIDADA_SIN_ENVIO', $factura->electronic_status);
        $this->assertSame('cufe-sandbox', $factura->cufe);
        $this->assertSame('zip-1', $factura->provider_document_id);
        $this->assertSame('PD94bWw=', $factura->dian_response['data']['xml']);
        $this->assertNotNull($factura->billing_provider_uuid);

        $this->assertDatabaseHas('electronic_invoices', [
            'invoice_uuid' => $factura->uuid,
            'status' => 'VALIDADA_SIN_ENVIO',
            'cufe' => 'cufe-sandbox',
            'provider_document_id' => 'zip-1',
        ]);

        Http::assertSent(function ($peticion) {
            return str_starts_with($peticion->url(), self::BASE.'/api/ubl2.1/invoice/')
                && $peticion->hasHeader('Authorization', 'Bearer token_de_prueba')
                && $peticion['send'] === false
                && $peticion['type_document_id'] === 1
                && $peticion['number'] === 1
                && $peticion['resolution_number'] === '18760000001'
                && $peticion['legal_monetary_totals']['payable_amount'] === '119000.00'
                && $peticion['customer']['identification_number'] === 900226715
                && $peticion['invoice_lines'][0]['tax_totals'][0]['tax_amount'] === '19000.00';
        });
    }

    public function test_send_true_va_sin_test_set_y_reporta_aceptada(): void
    {
        $this->proveedor(['send_to_dian' => 1, 'test_set_id' => null]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'success' => true,
                'data' => ['cufe' => 'cufe-prod', 'IsValid' => true],
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('ACEPTADA_DIAN', $respuesta['estado']);
        $this->assertSame('cufe-prod', $factura->refresh()->cufe);

        Http::assertSent(fn ($peticion) => $peticion->url() === self::BASE.'/api/ubl2.1/invoice'
            && $peticion['send'] === true);
    }

    public function test_emision_falla_cerrado_sin_url_o_token(): void
    {
        $this->proveedor(['server_url' => null]);
        $factura = $this->factura();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL del API');
        $this->puertoFacturas()->emitir($factura);
    }

    public function test_sin_resolucion_omite_la_clave_y_deja_que_apidia_resuelva(): void
    {
        // En habilitación ApiDian resuelve su resolución activa si la clave no
        // viaja; enviarla vacía ("") la hace rechazar el documento.
        $this->proveedor();
        $factura = $this->factura(['billing_resolution_uuid' => null]);

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response([
                'success' => true,
                'data' => ['cufe' => 'cufe-sin-resolucion', 'IsValid' => true],
            ], 200),
        ]);

        $this->puertoFacturas()->emitir($factura);

        Http::assertSent(fn ($peticion) => ! array_key_exists('resolution_number', $peticion->data()));
    }

    public function test_factura_de_contado_omite_vencimiento_y_plazo(): void
    {
        $this->proveedor();
        $factura = $this->factura(['due_date' => null]);

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response([
                'success' => true,
                'data' => ['cufe' => 'cufe-contado', 'IsValid' => true],
            ], 200),
        ]);

        $this->puertoFacturas()->emitir($factura);

        Http::assertSent(function ($peticion) {
            $pago = $peticion['payment_form'];

            return $pago['payment_form'] === 1
                && ! array_key_exists('payment_due_date', $pago)
                && ! array_key_exists('duration_measure', $pago);
        });
    }

    public function test_medio_de_pago_se_sobreescribe_por_empresa(): void
    {
        $this->proveedor(['settings' => json_encode(['payment_method_id' => 30])]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response([
                'success' => true,
                'data' => ['cufe' => 'cufe-pago', 'IsValid' => true],
            ], 200),
        ]);

        $this->puertoFacturas()->emitir($factura);

        Http::assertSent(fn ($peticion) => $peticion['payment_form']['payment_method_id'] === 30);
    }

    public function test_token_invalido_se_reporta_con_mensaje_claro(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El token de ApiDian es inválido o expiró');
        $this->puertoFacturas()->emitir($factura);
    }

    public function test_renueva_el_token_con_el_login_json_y_reintenta(): void
    {
        $uuidProveedor = $this->proveedor([
            'client_id' => 'sandbox@pruebas.test',
            'client_password' => \Illuminate\Support\Facades\Crypt::encryptString('clave_portal'),
            'client_secret' => \Illuminate\Support\Facades\Crypt::encryptString('token_vencido'),
        ]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::sequence()
                ->push(['message' => 'Unauthenticated.'], 401)
                ->push(['success' => true, 'data' => ['cufe' => 'cufe-renovado', 'IsValid' => true]], 200),
            self::BASE.'/api/auth/login' => Http::response([
                'success' => true,
                'access_token' => 'jwt_nuevo',
                'token_type' => 'bearer',
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cufe-renovado', $respuesta['cufe']);

        // El login viaja como en la colección: email + password.
        Http::assertSent(fn ($peticion) => $peticion->url() === self::BASE.'/api/auth/login'
            && $peticion['email'] === 'sandbox@pruebas.test'
            && $peticion['password'] === 'clave_portal');

        // El reintento usa el token nuevo y queda guardado cifrado.
        Http::assertSent(fn ($peticion) => str_contains($peticion->url(), '/api/ubl2.1/invoice/set123')
            && $peticion->hasHeader('Authorization', 'Bearer jwt_nuevo'));

        $this->assertSame('jwt_nuevo', BillingProvider::where('uuid', $uuidProveedor)->firstOrFail()->client_secret);
    }

    public function test_login_fallido_reporta_error_claro(): void
    {
        $this->proveedor([
            'client_id' => 'sandbox@pruebas.test',
            'client_password' => \Illuminate\Support\Facades\Crypt::encryptString('clave_incorrecta'),
        ]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response(['message' => 'Unauthenticated.'], 401),
            self::BASE.'/api/auth/login' => Http::response(['message' => 'Credenciales inválidas'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se pudo renovar el token de ApiDian');
        $this->puertoFacturas()->emitir($factura);
    }

    public function test_sin_proveedor_activo_usa_el_driver_nulo(): void
    {
        // Sin fila de proveedor: el proceso resteado de las fases 1 y 2 se mantiene.
        $factura = $this->factura();

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('PENDIENTE_DIAN', $respuesta['estado']);
        $factura->refresh();
        $this->assertSame('PENDIENTE_DIAN', $factura->electronic_status);
        $this->assertStringContainsString('<Invoice', (string) $factura->ubl_document);
        $this->assertNull($factura->cufe);
    }

    public function test_proveedor_con_otro_driver_caen_en_nulo_y_advierten(): void
    {
        $this->proveedor(['driver' => 'ficticio']);
        $factura = $this->factura();

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('PENDIENTE_DIAN', $respuesta['estado']);
        $this->assertStringContainsString('<Invoice', (string) $factura->refresh()->ubl_document);
    }

    public function test_emite_nota_credito_con_referencia_de_factura(): void
    {
        $this->proveedor();

        $factura = $this->factura(['cufe' => 'cufe-factura-origen']);

        $uuidNota = $this->insertar('credit_notes', [
            'company_uuid' => $this->empresa,
            'invoice_uuid' => $factura->uuid,
            'third_party_uuid' => $this->cliente,
            'note_number' => 'NC-0001',
            'correction_concept_code' => '2',
            'observation' => 'Anulación de factura',
            'issue_date' => '2026-10-06',
            'status' => 'EMITIDA_INTERNA',
            'electronic_status' => 'PENDIENTE_DIAN',
            'subtotal' => 20000,
            'total_discount' => 0,
            'total_tax' => 3800,
            'total_withholding' => 0,
            'total' => 23800,
        ]);
        $this->insertar('credit_note_items', [
            'note_uuid' => $uuidNota,
            'order_index' => 1,
            'description' => 'Devolución parcial',
            'quantity' => 1,
            'unit_price' => 20000,
            'tax_rate' => 19,
            'tax_amount' => 3800,
            'line_total' => 23800,
        ]);
        $nota = \App\Models\CreditNote::where('uuid', $uuidNota)->firstOrFail();

        Http::fake([
            self::BASE.'/api/ubl2.1/credit-note/set123' => Http::response([
                'success' => true,
                'data' => ['cude' => 'cude-nc-1', 'IsValid' => true],
            ], 200),
        ]);

        $respuesta = $this->puertoNotas()->emitirNota($nota);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cude-nc-1', $nota->refresh()->cude);

        Http::assertSent(function ($peticion) use ($factura) {
            return str_contains($peticion->url(), '/api/ubl2.1/credit-note/set123')
                && $peticion['send'] === false
                && $peticion['type_document_id'] === 4
                && $peticion['billing_reference']['uuid'] === $factura->cufe
                && $peticion['customer']['identification_number'] === 900226715
                && count($peticion['credit_note_lines']) === 1;
        });
    }

    public function test_nota_debito_va_con_su_tipo_documental(): void
    {
        $this->proveedor();
        $factura = $this->factura(['cufe' => 'cufe-factura-origen']);

        $uuidNota = $this->insertar('debit_notes', [
            'company_uuid' => $this->empresa,
            'invoice_uuid' => $factura->uuid,
            'third_party_uuid' => $this->cliente,
            'note_number' => 'ND-0001',
            'correction_concept_code' => '1',
            'issue_date' => '2026-10-06',
            'status' => 'EMITIDA_INTERNA',
            'electronic_status' => 'PENDIENTE_DIAN',
            'subtotal' => 50000,
            'total' => 59500,
            'total_tax' => 9500,
            'total_withholding' => 0,
            'total_discount' => 0,
        ]);
        $nota = \App\Models\DebitNote::where('uuid', $uuidNota)->firstOrFail();

        Http::fake([
            self::BASE.'/api/ubl2.1/debit-note/set123' => Http::response([
                'success' => true,
                'data' => ['cude' => 'cude-nd-1'],
            ], 200),
        ]);

        $respuesta = $this->puertoNotas()->emitirNota($nota);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        Http::assertSent(fn ($peticion) => str_contains($peticion->url(), '/api/ubl2.1/debit-note/set123')
            && $peticion['type_document_id'] === 5
            && $peticion['send'] === false);
    }

    public function test_consulta_estado_apunta_a_la_clave_firmada(): void
    {
        $this->proveedor();
        $factura = $this->factura(['cufe' => 'cufe-consultado']);

        Http::fake([
            self::BASE.'/api/ubl2.1/status/document/cufe-consultado' => Http::response([
                'success' => true,
                'data' => ['IsValid' => true, 'status' => 'ACP'],
            ], 200),
        ]);

        $estado = $this->puertoFacturas()->consultarEstado($factura);

        $this->assertSame('ACEPTADA_DIAN', $estado['estado']);
        $this->assertSame('cufe-consultado', $estado['cufe']);
    }

    public function test_estado_rechazado_se_reporta_como_rechazado(): void
    {
        $this->proveedor();
        $factura = $this->factura(['cufe' => 'cufe-rechazado']);

        Http::fake([
            self::BASE.'/api/ubl2.1/status/document/cufe-rechazado' => Http::response([
                'success' => true,
                'data' => ['IsValid' => false, 'status' => 'RECHAZADO'],
            ], 200),
        ]);

        $estado = $this->puertoFacturas()->consultarEstado($factura);

        $this->assertSame('RECHAZADA_DIAN', $estado['estado']);
    }

    public function test_respuesta_con_errores_marca_rechazada(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/api/ubl2.1/invoice/set123' => Http::response([
                'success' => true,
                'data' => ['IsValid' => false, 'Errors' => ['El proceso esta replicado']],
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('RECHAZADA_DIAN', $respuesta['estado']);
        $this->assertSame('RECHAZADA_DIAN', $factura->refresh()->electronic_status);
    }
}
