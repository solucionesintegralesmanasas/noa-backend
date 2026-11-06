<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingProvider;
use App\Models\CommercialInvoice;
use App\Services\Billing\ContratoFacturacionElectronica;
use App\Services\Billing\ContratoNotasElectronica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Driver Factus (proveedor tecnológico, enfoque SERVICIOS).
 *
 * Factus recibe facturas en /v2/bills/validate y calcula los totales; NOA
 * envía reference_code (idempotencia), numbering_range_id, cliente e ítems de
 * servicio. El transporte y la renovación OAuth2 se prueban con Http::fake().
 */
class FactusFacturacionTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $cliente;

    private string $resolucion;

    private const BASE = 'https://factus-sandbox.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Factus Test']);
        // Contexto de empresa requerido por el scope BelongsToCompany (Issue #2).
        request()->attributes->set('current_company_uuid', $this->empresa);
        $this->cliente = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'JURIDICA',
            'document_number' => '900226715',
            'nit_check_digit' => '3',
            'company_name' => 'CLIENTE DE PRUEBA SAS',
            'trade_name' => 'CLIENTE PRUEBA',
            'email' => 'cliente@factus.test',
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
            'name' => 'Factus',
            'driver' => 'factus',
            'server_url' => self::BASE,
            'send_to_dian' => 0,
            'client_id' => '9e1a-4f2c',
            'client_secret' => Crypt::encryptString('app_secret'),
            'email' => 'usuario@factus.test',
            'client_password' => Crypt::encryptString('clave_portal'),
            'access_token' => Crypt::encryptString('token_factus'),
            'refresh_token' => Crypt::encryptString('refresh_factus'),
            'access_token_expires_at' => '2099-01-01 00:00:00',
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

    public function test_emite_factura_de_servicio_y_guarda_cufe_y_numero(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'SETP990002519', 'cufe' => 'cufe-factus', 'is_validated' => true, 'pdf_url' => 'https://factus.test/pdf'],
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cufe-factus', $respuesta['cufe']);
        $this->assertSame('SETP990002519', $respuesta['numero']);

        $factura->refresh();
        $this->assertSame('VALIDADA_SIN_ENVIO', $factura->electronic_status);
        $this->assertSame('cufe-factus', $factura->cufe);
        $this->assertSame('SETP990002519', $factura->provider_document_id);
        $this->assertNotNull($factura->billing_provider_uuid);

        $this->assertDatabaseHas('electronic_invoices', [
            'invoice_uuid' => $factura->uuid,
            'status' => 'VALIDADA_SIN_ENVIO',
            'cufe' => 'cufe-factus',
            'provider_document_id' => 'SETP990002519',
        ]);

        Http::assertSent(function ($peticion) {
            $payload = $peticion->data();

            return str_ends_with($peticion->url(), '/v2/bills/validate')
                && $peticion->hasHeader('Authorization', 'Bearer token_factus')
                && ($payload['document'] ?? null) === '01'
                && ($payload['reference_code'] ?? null) === 'TSB-00000001'
                && ($payload['operation_type'] ?? null) === '10'
                && ($payload['customer']['identification_document_code'] ?? null) === '31'
                && ($payload['customer']['identification'] ?? null) === '900226715'
                && ($payload['customer']['dv'] ?? null) === '3'
                && ($payload['customer']['legal_organization_code'] ?? null) === '1'
                && ($payload['items'][0]['unit_measure_code'] ?? null) === '94'
                && ($payload['items'][0]['taxes'][0]['code'] ?? null) === '01'
                && ! array_key_exists('legal_monetary_totals', $payload)
                && ! array_key_exists('resolution_number', $payload);
        });
    }

    public function test_send_true_reporta_aceptada(): void
    {
        $this->proveedor(['send_to_dian' => 1]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'SETP990002520', 'cufe' => 'cufe-prod', 'is_validated' => true],
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('ACEPTADA_DIAN', $respuesta['estado']);
        $this->assertSame('cufe-prod', $factura->refresh()->cufe);
    }

    public function test_emision_falla_cerrado_sin_url(): void
    {
        $this->proveedor(['server_url' => null]);
        $factura = $this->factura();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('URL del API');
        $this->puertoFacturas()->emitir($factura);
    }

    public function test_factura_de_contado_omite_la_fecha_de_vencimiento(): void
    {
        $this->proveedor();
        $factura = $this->factura(['due_date' => null]);

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'SETP990002521', 'cufe' => 'cufe-contado', 'is_validated' => true],
            ], 200),
        ]);

        $this->puertoFacturas()->emitir($factura);

        Http::assertSent(function ($peticion) {
            $detalle = $peticion->data()['payment_details'][0] ?? [];

            return ($detalle['payment_form'] ?? null) === '1'
                && ! array_key_exists('due_date', $detalle);
        });
    }

    public function test_factura_a_credito_incluye_el_vencimiento_en_el_pago(): void
    {
        $this->proveedor();
        $factura = $this->factura(); // due_date 2026-11-04

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'SETP990002522', 'cufe' => 'cufe-credito', 'is_validated' => true],
            ], 200),
        ]);

        $this->puertoFacturas()->emitir($factura);

        Http::assertSent(function ($peticion) {
            $detalle = $peticion->data()['payment_details'][0] ?? [];

            return ($detalle['payment_form'] ?? null) === '2'
                && ($detalle['due_date'] ?? null) === '2026-11-04';
        });
    }

    public function test_emite_nota_credito_con_referencia_de_factura(): void
    {
        $this->proveedor();
        $factura = $this->factura([
            'cufe' => 'cufe-factura-origen',
            'provider_document_id' => 'SETP990002519',
        ]);

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
            self::BASE.'/v2/credit-notes/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'NC-0001', 'cude' => 'cude-nc-1', 'is_validated' => true],
            ], 200),
        ]);

        $respuesta = $this->puertoNotas()->emitirNota($nota);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cude-nc-1', $nota->refresh()->cude);

        Http::assertSent(function ($peticion) {
            $payload = $peticion->data();

            return str_ends_with($peticion->url(), '/v2/credit-notes/validate')
                && ($payload['customization_id'] ?? null) === '20'
                && ($payload['bill_number'] ?? null) === 'SETP990002519'
                && ($payload['correction_concept_code'] ?? null) === '2'
                && count($payload['items'] ?? []) === 1;
        });
    }

    public function test_nota_debito_va_a_su_endpoint(): void
    {
        $this->proveedor();
        $factura = $this->factura([
            'cufe' => 'cufe-factura-origen',
            'provider_document_id' => 'SETP990002519',
        ]);

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
            self::BASE.'/v2/debit-notes/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'ND-0001', 'cude' => 'cude-nd-1'],
            ], 200),
        ]);

        $respuesta = $this->puertoNotas()->emitirNota($nota);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        Http::assertSent(fn ($peticion) => str_ends_with($peticion->url(), '/v2/debit-notes/validate')
            && ($peticion->data()['customization_id'] ?? null) === '30'
            && ($peticion->data()['bill_number'] ?? null) === 'SETP990002519');
    }

    public function test_respuesta_con_errores_marca_rechazada(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'rejected',
                'data' => ['is_validated' => false, 'errors' => ['El documento fue rechazado']],
            ], 422),
            self::BASE.'/v2/bills/destroy/reference/*' => Http::response(['status' => 'OK'], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('RECHAZADA_DIAN', $respuesta['estado']);
        $this->assertSame('RECHAZADA_DIAN', $factura->refresh()->electronic_status);
        Http::assertSent(fn ($peticion) => $peticion->method() === 'DELETE'
            && str_ends_with($peticion->url(), '/v2/bills/destroy/reference/TSB-00000001'));
    }

    public function test_servicio_excluido_de_iva_envia_is_excluded_y_links_v2(): void
    {
        $this->proveedor(['settings' => json_encode(['numbering_ranges' => ['bills' => 389]])]);
        $uuidMedioPago = $this->insertar('payment_methods', [
            'dian_code' => '47',
            'name' => 'Transferencia',
            'is_active' => 1,
        ]);
        $uuidFactura = $this->insertar('commercial_invoices', [
            'company_uuid' => $this->empresa,
            'invoice_type' => 'FACTURA_VENTA',
            'status' => 'EMITIDA_INTERNA',
            'invoice_number' => 'TSB-00000002',
            'issue_date' => '2026-10-05',
            'third_party_uuid' => $this->cliente,
            'billing_resolution_uuid' => $this->resolucion,
            'payment_method_uuid' => $uuidMedioPago,
            'subtotal' => 1900000,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_withholding' => 19000,
            'invoice_total' => 1881000,
            'electronic_status' => 'PENDIENTE_DIAN',
        ]);
        $uuidItem = $this->insertar('commercial_invoice_items', [
            'invoice_uuid' => $uuidFactura,
            'order_index' => 1,
            'description' => 'Servicio de transporte terrestre excluido',
            'quantity' => 1,
            'unit_price' => 1900000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 1900000,
        ]);
        $this->insertar('commercial_invoice_item_withholdings', [
            'invoice_item_uuid' => $uuidItem,
            'withholding_type' => 'RETEICA',
            'withholding_rate' => 1,
            'amount' => 19000,
        ]);

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'Created',
                'data' => [
                    'number' => 'SETP990002530',
                    'cufe' => 'cufe-excluido',
                    'is_validated' => true,
                    'errors' => ['RUT01' => 'Regla: RUT01, Notificación: validar RUT'],
                    'links' => [
                        'public_url' => 'https://factus.test/ver/SETP990002530',
                        'qr' => 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=cufe-excluido',
                    ],
                ],
            ], 201),
        ]);

        $factura = CommercialInvoice::where('uuid', $uuidFactura)->firstOrFail();
        $respuesta = $this->puertoFacturas()->emitir($factura);

        // Las notificaciones informativas (RUT01) NO marcan la factura como rechazada.
        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('https://factus.test/ver/SETP990002530', $respuesta['pdf_url']);
        $this->assertSame('https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=cufe-excluido', $respuesta['qr']);

        Http::assertSent(function ($peticion) {
            if (! str_ends_with($peticion->url(), '/v2/bills/validate')) {
                return false;
            }
            $payload = $peticion->data();
            $pago = $payload['payment_details'][0] ?? [];
            $item = $payload['items'][0] ?? [];

            return ($payload['numbering_range_id'] ?? null) === 389
                && ($pago['payment_method_code'] ?? null) === '47'
                && ($pago['amount'] ?? null) === '1900000.00'
                && ($item['taxes'] ?? null) === [['is_excluded' => true]]
                && ! array_key_exists('withholding_taxes', $item);
        });
    }

    public function test_error_validacion_422_lanza_excepcion_con_campos_de_factus(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'Validation error',
                'message' => 'Error de validación',
                'data' => [
                    'errors' => [
                        'customer.municipality_code' => ['El código de municipio no es válido.'],
                    ],
                ],
            ], 422),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El código de municipio no es válido.');
        $this->puertoFacturas()->emitir($factura);
    }

    public function test_conflicto_409_se_explica_con_mensaje_claro(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::response([
                'message' => 'Se encontró una factura pendiente por enviar a la DIAN. Error 409 Conflict',
            ], 409),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pendiente por enviar a la DIAN');
        $this->puertoFacturas()->emitir($factura);
    }

    public function test_renueva_el_token_con_refresh_oauth2_y_reintenta(): void
    {
        $uuidProveedor = $this->proveedor([
            'access_token' => Crypt::encryptString('token_viejo'),
        ]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/v2/bills/validate' => Http::sequence()
                ->push(['message' => 'Unauthenticated.'], 401)
                ->push(['status' => 'created', 'data' => ['number' => 'SETP990002523', 'cufe' => 'cufe-renovado', 'is_validated' => true]], 200),
            self::BASE.'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'token_nuevo',
                'refresh_token' => 'refresh_nuevo',
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cufe-renovado', $respuesta['cufe']);

        // El refresh viaja con el refresh token guardado y no se llama al login.
        Http::assertSent(fn ($peticion) => $peticion->url() === self::BASE.'/oauth/token'
            && $peticion['grant_type'] === 'refresh_token'
            && $peticion['refresh_token'] === 'refresh_factus');
        Http::assertSent(fn ($peticion) => str_ends_with($peticion->url(), '/v2/bills/validate')
            && $peticion->hasHeader('Authorization', 'Bearer token_nuevo'));

        $this->assertSame('token_nuevo', BillingProvider::where('uuid', $uuidProveedor)->firstOrFail()->access_token);
    }

    public function test_login_oauth2_obtiene_tokens_desde_el_portal(): void
    {
        $uuidProveedor = $this->proveedor(['access_token' => null, 'access_token_expires_at' => null]);
        $factura = $this->factura();

        Http::fake([
            self::BASE.'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'token_login',
                'refresh_token' => 'refresh_login',
            ], 200),
            self::BASE.'/v2/bills/validate' => Http::response([
                'status' => 'created',
                'data' => ['number' => 'SETP990002524', 'cufe' => 'cufe-login', 'is_validated' => true],
            ], 200),
        ]);

        $respuesta = $this->puertoFacturas()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);

        Http::assertSent(fn ($peticion) => $peticion->url() === self::BASE.'/oauth/token'
            && $peticion['grant_type'] === 'password'
            && $peticion['client_id'] === '9e1a-4f2c'
            && $peticion['username'] === 'usuario@factus.test'
            && $peticion['password'] === 'clave_portal');

        $guardado = BillingProvider::where('uuid', $uuidProveedor)->firstOrFail();
        $this->assertSame('token_login', $guardado->access_token);
        $this->assertSame('refresh_login', $guardado->refresh_token);
        $this->assertNotNull($guardado->access_token_expires_at);
    }
}
