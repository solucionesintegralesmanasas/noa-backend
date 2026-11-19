<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommercialInvoice;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Services\Billing\ContratoFacturacionElectronica;
use App\Services\Billing\ContratoNotasElectronica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Driver ApiDian (FACTW): factura de venta de servicios, conviviendo con Factus.
 *
 * FACTW recibe el payload en /api/ubl2.1/invoice con `send` (transmisión real),
 * ids de catálogo propios (no códigos DIAN), y el número + resolución de SU rango
 * activo (no los internos de NOA) y sin retenciones. El transporte se prueba con Http::fake().
 */
class ApidianFacturacionTest extends TestCase
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
        // Contexto de empresa requerido por el scope BelongsToCompany (Issue #2).
        request()->attributes->set('current_company_uuid', $this->empresa);

        $departamento = $this->insertar('departments', ['dane_code' => '11', 'name' => 'Bogotá']);
        $ciudad = $this->insertar('cities', [
            'department_uuid' => $departamento,
            'dane_code' => '11001',
            'name' => 'Bogotá D.C.',
        ]);
        $tipoDoc = $this->insertar('type_of_documents', ['name' => 'NIT', 'prefix' => 'NIT']);
        $responsabilidad = $this->insertar('tax_responsibilities', ['code' => 'O-13', 'name' => 'Gran contribuyente']);

        $this->cliente = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'JURIDICA',
            'document_type_uuid' => $tipoDoc,
            'document_number' => '900226715',
            'nit_check_digit' => '3',
            'company_name' => 'CLIENTE DE PRUEBA SAS',
            'trade_name' => 'CLIENTE PRUEBA',
            'email' => 'cliente@apidian.test',
            'municipality_uuid' => $ciudad,
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $responsabilidad,
        ]);

        $this->resolucion = $this->insertar('billing_resolutions', [
            'company_uuid' => $this->empresa,
            'resolution_type_code' => '01',
            'resolution_number' => '12345',
            'invoice_prefix' => 'SETP',
            'invoice_start_number' => 980000000,
            'invoice_end_number' => 995000000,
            'last_consecutive' => 5,
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
            'send_to_dian' => 0,
            'client_password' => Crypt::encryptString('whs_token_test'),
            'is_active' => 1,
        ]);
    }

    private function factura(array $datos = []): CommercialInvoice
    {
        $uuidFactura = $this->insertar('commercial_invoices', $datos + [
            'company_uuid' => $this->empresa,
            'invoice_type' => 'FACTURA_VENTA',
            'status' => 'EMITIDA_INTERNA',
            'invoice_number' => 'TSB1',
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

    private function puerto(): ContratoFacturacionElectronica
    {
        return app(ContratoFacturacionElectronica::class);
    }

    private function puertoNotas(): ContratoNotasElectronica
    {
        return app(ContratoNotasElectronica::class);
    }

    /** Fake del rango de numeración de FACTW: dos rangos solapados, se elige el de menor id. */
    private function fakeRangos(int $next = 980000006): array
    {
        return [
            self::BASE.'/api/ubl2.1/numbering-ranges' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 19, 'type_document_id' => 1, 'active' => true, 'prefix' => 'SETP', 'resolution' => '0', 'next' => $next, 'next_consecutive' => "SETP{$next}"],
                    ['id' => 55, 'type_document_id' => 1, 'active' => true, 'prefix' => 'SETP', 'resolution' => '18764088341384', 'next' => $next, 'next_consecutive' => "SETP{$next}"],
                ],
            ], 200),
        ];
    }

    public function test_emite_con_send_false_y_guarda_document_id_numerico(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'is_valid' => true,
                'status_code' => null,
                'cufe' => 'cufe-apidian',
                'number' => 'SETP980000006',
                'document_id' => 507,
                'pdf_download_link' => 'https://apidian.test/pdf/507',
                'qr_link' => 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=cufe-apidian',
            ], 200),
        ]);

        $respuesta = $this->puerto()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cufe-apidian', $respuesta['cufe']);
        $this->assertSame(507, $respuesta['numero']);

        $factura->refresh();
        $this->assertSame('VALIDADA_SIN_ENVIO', $factura->electronic_status);
        $this->assertSame('cufe-apidian', $factura->cufe);
        $this->assertSame(507, (int) $factura->provider_document_id);
        $this->assertNotNull($factura->billing_provider_uuid);

        $this->assertDatabaseHas('electronic_invoices', [
            'invoice_uuid' => $factura->uuid,
            'status' => 'VALIDADA_SIN_ENVIO',
            'cufe' => 'cufe-apidian',
        ]);

        Http::assertSent(function ($peticion) {
            $payload = $peticion->data();

            return str_ends_with($peticion->url(), '/api/ubl2.1/invoice')
                && $peticion->hasHeader('Authorization', 'Bearer whs_token_test')
                && ($payload['number'] ?? null) === 980000006
                && ($payload['send'] ?? null) === false
                && ($payload['type_document_id'] ?? null) === 1
                && ($payload['resolution_number'] ?? null) === '0'
                && ($payload['customer']['identification_number'] ?? null) === '900226715'
                && ($payload['customer']['dv'] ?? null) === '3'
                && ($payload['customer']['type_document_identification_id'] ?? null) === 6
                && ($payload['customer']['type_organization_id'] ?? null) === 1
                && ($payload['customer']['type_liability_id'] ?? null) === 7
                && ($payload['customer']['municipality_id'] ?? null) === 149
                && ($payload['customer']['type_regime_id'] ?? null) === 1
                && ($payload['legal_monetary_totals']['payable_amount'] ?? null) === '119000.00'
                && ($payload['invoice_lines'][0]['unit_measure_id'] ?? null) === 70
                && ($payload['invoice_lines'][0]['type_item_identification_id'] ?? null) === 4;
        });
    }

    public function test_send_true_con_status_code_99_queda_pendiente(): void
    {
        $this->proveedor(['send_to_dian' => 1]);
        $factura = $this->factura();

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'is_valid' => true,
                'status_code' => '99',
                'cufe' => 'cufe-pendiente',
                'number' => 'SETP980000006',
                'document_id' => 508,
            ], 200),
        ]);

        $respuesta = $this->puerto()->emitir($factura);

        $this->assertSame('PENDIENTE_DIAN', $respuesta['estado']);
        $this->assertSame('PENDIENTE_DIAN', $factura->refresh()->electronic_status);
    }

    public function test_send_true_sin_fault_queda_aceptada(): void
    {
        $this->proveedor(['send_to_dian' => 1]);
        $factura = $this->factura();

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'is_valid' => true,
                'status_code' => null,
                'cufe' => 'cufe-aceptada',
                'number' => 'SETP980000006',
                'document_id' => 509,
            ], 200),
        ]);

        $respuesta = $this->puerto()->emitir($factura);

        $this->assertSame('ACEPTADA_DIAN', $respuesta['estado']);
    }

    public function test_respuesta_invalida_marca_rechazada_y_no_descarta_retencion(): void
    {
        $this->proveedor();
        $factura = $this->factura([
            'total_withholding' => 5000,
            'invoice_total' => 114000,
        ]);

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'is_valid' => false,
                'errors_messages' => ['customer.municipality_id (11001) no existe en el catálogo.'],
            ], 422),
        ]);

        $respuesta = $this->puerto()->emitir($factura);

        $this->assertSame('RECHAZADA_DIAN', $respuesta['estado']);
        $this->assertSame('RECHAZADA_DIAN', $factura->refresh()->electronic_status);
    }

    public function test_token_invalido_401_lanza_error_claro_y_no_marca_rechazada(): void
    {
        $this->proveedor();
        $factura = $this->factura();

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'success' => false,
                'message' => 'No autenticado.',
                'error_id' => 'err_abc123',
            ], 401),
        ]);

        try {
            $this->puerto()->emitir($factura);
            $this->fail('Se esperaba una excepción por credenciales inválidas.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se pudo autenticar con ApiDian', $e->getMessage());
        }

        // No se marca rechazada: queda pendiente para reintentar con un token válido.
        $this->assertSame('PENDIENTE_DIAN', $factura->refresh()->electronic_status);
    }

    public function test_login_con_correo_y_password_obtiene_jwt_y_emite(): void
    {
        $this->proveedor([
            'client_password' => Crypt::encryptString('clave_apidian'),
            'email' => 'sandbox@apidian.test',
            'access_token' => null,
        ]);
        $factura = $this->factura();

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/auth/login' => Http::response([
                'success' => true,
                'access_token' => 'jwt_login',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'is_valid' => true,
                'status_code' => null,
                'cufe' => 'cufe-jwt',
                'number' => 'SETP980000006',
                'document_id' => 507,
            ], 200),
        ]);

        $respuesta = $this->puerto()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);

        // El login viajó con email+password y la emisión con el JWT devuelto.
        Http::assertSent(fn ($p) => $p->url() === self::BASE.'/api/auth/login'
            && $p['email'] === 'sandbox@apidian.test'
            && $p['password'] === 'clave_apidian');
        Http::assertSent(fn ($p) => str_ends_with($p->url(), '/api/ubl2.1/invoice')
            && $p->hasHeader('Authorization', 'Bearer jwt_login'));
    }

    public function test_refresca_el_jwt_ante_401_y_reintenta(): void
    {
        $this->proveedor([
            'client_password' => Crypt::encryptString('clave_apidian'),
            'email' => 'sandbox@apidian.test',
            'access_token' => Crypt::encryptString('jwt_viejo'),
            'access_token_expires_at' => '2099-01-01 00:00:00',
        ]);
        $factura = $this->factura();

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::sequence()
                ->push(['success' => false, 'message' => 'No autenticado.'], 401)
                ->push(['is_valid' => true, 'status_code' => null, 'cufe' => 'cufe-refrescado', 'number' => 'SETP980000006', 'document_id' => 507], 200),
            self::BASE.'/api/auth/refresh' => Http::response([
                'success' => true,
                'access_token' => 'jwt_nuevo',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $respuesta = $this->puerto()->emitir($factura);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cufe-refrescado', $respuesta['cufe']);

        Http::assertSent(fn ($p) => $p->url() === self::BASE.'/api/auth/refresh'
            && $p->hasHeader('Authorization', 'Bearer jwt_viejo'));
        Http::assertSent(fn ($p) => str_ends_with($p->url(), '/api/ubl2.1/invoice')
            && $p->hasHeader('Authorization', 'Bearer jwt_nuevo'));
    }

    public function test_la_retencion_no_se_resta_del_payable_de_apidian(): void
    {
        $this->proveedor();
        $factura = $this->factura([
            'total_withholding' => 5000,
            'invoice_total' => 114000,
        ]);

        Http::fake($this->fakeRangos() + [
            self::BASE.'/api/ubl2.1/invoice' => Http::response([
                'is_valid' => true,
                'status_code' => null,
                'cufe' => 'cufe-retencion',
                'number' => 'SETP980000006',
                'document_id' => 510,
            ], 200),
        ]);

        $this->puerto()->emitir($factura);

        Http::assertSent(function ($peticion) {
            if (! str_ends_with($peticion->url(), '/api/ubl2.1/invoice')) {
                return false;
            }
            $totales = $peticion->data()['legal_monetary_totals'] ?? [];

            // ApiDian no envía retenciones: payable = tax_inclusive.
            return ($totales['tax_inclusive_amount'] ?? null) === '119000.00'
                && ($totales['payable_amount'] ?? null) === '119000.00';
        });
    }

    public function test_emite_nota_credito_con_referencia_y_rango_apidian(): void
    {
        $this->proveedor(['send_to_dian' => 1]);
        $factura = $this->factura([
            'cufe' => 'cufe-factura-origen',
            'provider_document_id' => '507',
            'dian_response' => json_encode(['number' => 'SETP980000006']),
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
        $nota = CreditNote::where('uuid', $uuidNota)->firstOrFail();

        Http::fake([
            self::BASE.'/api/ubl2.1/numbering-ranges' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 20, 'type_document_id' => 4, 'active' => true, 'prefix' => 'NC', 'resolution' => '0', 'next' => 15, 'next_consecutive' => 'NC15'],
                ],
            ], 200),
            self::BASE.'/api/ubl2.1/credit-note' => Http::response([
                'is_valid' => true,
                'cufe' => 'cude-apidian-nc-15',
                'number' => 'NC15',
                'document_id' => 610,
            ], 200),
        ]);

        $respuesta = $this->puertoNotas()->emitirNota($nota);

        $this->assertSame('ACEPTADA_DIAN', $respuesta['estado']);
        $this->assertSame('cude-apidian-nc-15', $nota->refresh()->cude);
        $this->assertSame('ACEPTADA_DIAN', $nota->electronic_status);

        Http::assertSent(function ($peticion) {
            if (! str_ends_with($peticion->url(), '/api/ubl2.1/credit-note')) {
                return false;
            }
            $p = $peticion->data();

            return ($p['type_document_id'] ?? null) === 4
                && ($p['number'] ?? null) === 15
                && ($p['send'] ?? null) === true
                && ($p['billing_reference']['number'] ?? null) === 'SETP980000006'
                && ($p['billing_reference']['uuid'] ?? null) === 'cufe-factura-origen'
                && ($p['discrepancy']['response_code'] ?? null) === 2
                && count($p['credit_note_lines'] ?? []) === 1
                && ! array_key_exists('debit_note_lines', $p);
        });
    }

    public function test_emite_nota_debito_en_su_endpoint_y_con_debit_note_lines(): void
    {
        $this->proveedor();
        $factura = $this->factura([
            'cufe' => 'cufe-factura-origen',
            'provider_document_id' => '507',
            'dian_response' => json_encode(['number' => 'SETP980000006']),
        ]);

        $uuidNota = $this->insertar('debit_notes', [
            'company_uuid' => $this->empresa,
            'invoice_uuid' => $factura->uuid,
            'third_party_uuid' => $this->cliente,
            'note_number' => 'ND-0001',
            'correction_concept_code' => '1',
            'observation' => 'Cobro adicional',
            'issue_date' => '2026-10-06',
            'status' => 'EMITIDA_INTERNA',
            'electronic_status' => 'PENDIENTE_DIAN',
            'subtotal' => 50000,
            'total_discount' => 0,
            'total_tax' => 9500,
            'total_withholding' => 0,
            'total' => 59500,
        ]);
        $this->insertar('debit_note_items', [
            'note_uuid' => $uuidNota,
            'order_index' => 1,
            'description' => 'Recargo nocturno',
            'quantity' => 1,
            'unit_price' => 50000,
            'tax_rate' => 19,
            'tax_amount' => 9500,
            'line_total' => 59500,
        ]);
        $nota = DebitNote::where('uuid', $uuidNota)->firstOrFail();

        Http::fake([
            self::BASE.'/api/ubl2.1/numbering-ranges' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 21, 'type_document_id' => 5, 'active' => true, 'prefix' => 'ND', 'resolution' => '0', 'next' => 8, 'next_consecutive' => 'ND8'],
                ],
            ], 200),
            self::BASE.'/api/ubl2.1/debit-note' => Http::response([
                'is_valid' => true,
                'cufe' => 'cude-apidian-nd-8',
                'number' => 'ND8',
                'document_id' => 611,
            ], 200),
        ]);

        $respuesta = $this->puertoNotas()->emitirNota($nota);

        $this->assertSame('VALIDADA_SIN_ENVIO', $respuesta['estado']);
        $this->assertSame('cude-apidian-nd-8', $nota->refresh()->cude);

        Http::assertSent(function ($peticion) {
            if (! str_ends_with($peticion->url(), '/api/ubl2.1/debit-note')) {
                return false;
            }
            $p = $peticion->data();

            return ($p['type_document_id'] ?? null) === 5
                && ($p['number'] ?? null) === 8
                && ($p['send'] ?? null) === false
                && ($p['discrepancy']['response_code'] ?? null) === 1
                && count($p['debit_note_lines'] ?? []) === 1
                && ! array_key_exists('credit_note_lines', $p);
        });
    }

    public function test_nota_sin_cufe_en_factura_lanza_error_claro(): void
    {
        $this->proveedor();
        $factura = $this->factura(['cufe' => null]);

        $uuidNota = $this->insertar('credit_notes', [
            'company_uuid' => $this->empresa,
            'invoice_uuid' => $factura->uuid,
            'third_party_uuid' => $this->cliente,
            'note_number' => 'NC-0002',
            'correction_concept_code' => '2',
            'issue_date' => '2026-10-06',
            'status' => 'EMITIDA_INTERNA',
            'electronic_status' => 'PENDIENTE_DIAN',
            'subtotal' => 10000,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_withholding' => 0,
            'total' => 10000,
        ]);
        $nota = CreditNote::where('uuid', $uuidNota)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CUFE');

        $this->puertoNotas()->emitirNota($nota);
    }

    public function test_consulta_estado_de_nota_en_apidian(): void
    {
        $this->proveedor(['send_to_dian' => 1]);
        $factura = $this->factura(['cufe' => 'cufe-factura-origen']);

        $uuidNota = $this->insertar('credit_notes', [
            'company_uuid' => $this->empresa,
            'invoice_uuid' => $factura->uuid,
            'third_party_uuid' => $this->cliente,
            'note_number' => 'NC-0003',
            'correction_concept_code' => '2',
            'issue_date' => '2026-10-06',
            'status' => 'EMITIDA_INTERNA',
            'electronic_status' => 'PENDIENTE_DIAN',
            'cude' => 'cude-nc-3',
            'dian_response' => json_encode(['number' => 'NC16']),
            'subtotal' => 10000,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_withholding' => 0,
            'total' => 10000,
        ]);
        $nota = CreditNote::where('uuid', $uuidNota)->firstOrFail();

        Http::fake([
            self::BASE.'/api/ubl2.1/documents*' => Http::response([
                'success' => true,
                'data' => ['number' => 'NC16', 'state_document_id' => 1],
            ], 200),
        ]);

        $estado = $this->puertoNotas()->consultarEstadoNota($nota);

        $this->assertSame('ACEPTADA_DIAN', $estado['estado']);
        $this->assertSame('NC16', $estado['numero']);
        $this->assertSame('cude-nc-3', $estado['cude']);
    }
}
