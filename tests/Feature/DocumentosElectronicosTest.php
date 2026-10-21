<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ElectronicDocument;
use App\Services\Billing\ElectronicDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Fase 3: documentos electrónicos genéricos ApiDian (soporte, equivalentes,
 * nómina 102/103, eventos RADIAN, lote y zip).
 *
 * El servicio guarda, transmite por el driver de la empresa y registra la
 * respuesta; solo se permite emitir/editar/borrar mientras no esté firmado.
 */
class DocumentosElectronicosTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private const BASE = 'https://apidian-sandbox.test';

    private const PAYLOAD_SOPORTE = [
        'number' => 'DS1001',
        'type_document_id' => 11,
        'date' => '2026-09-24',
        'time' => '08:00:00',
        'seller' => ['identification_number' => '12345678', 'name' => 'VENDEDOR SAS'],
        'payment_form' => ['payment_form' => 1, 'payment_method_id' => 10],
        'invoice_lines' => [['description' => 'Servicio', 'invoiced_quantity' => 1, 'price_amount' => 50000, 'line_extension_amount' => 50000]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Documentos ApiDian Test']);
        $this->insertar('billing_providers', [
            'company_uuid' => $this->empresa,
            'name' => 'ApiDian',
            'driver' => 'apidian',
            'server_url' => self::BASE,
            'test_set_id' => 'set123',
            'send_to_dian' => 0,
            'client_secret' => \Illuminate\Support\Facades\Crypt::encryptString('token_de_prueba'),
            'is_active' => 1,
        ]);
    }

    private function servicio(): ElectronicDocumentService
    {
        return app(ElectronicDocumentService::class);
    }

    private function datosDocumento(array $extra = []): array
    {
        return $extra + [
            'company_uuid' => $this->empresa,
            'api_document_type' => ElectronicDocument::TIPO_SOPORTE,
            'document_number' => 'DS1001',
            'payload' => self::PAYLOAD_SOPORTE,
        ];
    }

    public function test_inscribe_documento_soporte_sin_transmitir(): void
    {
        $documento = $this->servicio()->inscribir($this->datosDocumento());

        $this->assertSame('PENDIENTE_DIAN', $documento->electronic_status);
        $this->assertSame('DS1001', $documento->document_number);
        $this->assertNull($documento->document_key);
        $this->assertNull($documento->billing_provider_uuid);
        $this->assertTrue($documento->editable());
    }

    public function test_rechaza_tipo_desconocido(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->servicio()->inscribir($this->datosDocumento(['api_document_type' => 'factura-local']));
    }

    public function test_evento_radian_exige_codigo_valido(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->servicio()->inscribir($this->datosDocumento([
            'api_document_type' => ElectronicDocument::TIPO_EVENTO,
            'event_code' => '999',
        ]));
    }

    public function test_evento_radian_acepta_codigo_030_y_empuja_la_ruta_correcta(): void
    {
        $documento = $this->servicio()->inscribir($this->datosDocumento([
            'api_document_type' => ElectronicDocument::TIPO_EVENTO,
            'event_code' => '030',
            'document_number' => 'EV-030',
            'payload' => ['invoice_uuid' => 'cufe-destino', 'notes' => 'Acuse de recibo'],
        ]));

        Http::fake([
            self::BASE.'/api/ubl2.1/event/030/set123' => Http::response([
                'success' => true,
                'data' => ['cude' => 'cude-evento-030'],
            ], 200),
        ]);

        $emitido = $this->servicio()->emitir($documento->uuid);

        $this->assertSame('VALIDADA_SIN_ENVIO', $emitido->electronic_status);
        $this->assertSame('cude-evento-030', $emitido->document_key);
        $this->assertNotNull($emitido->billing_provider_uuid);
    }

    public function test_emitir_nomina_102_transmite_con_send_del_proveedor(): void
    {
        $documento = $this->servicio()->inscribir($this->datosDocumento([
            'api_document_type' => ElectronicDocument::TIPO_NOMINA,
            'document_number' => 'NIE-001',
            'payload' => ['consecutive' => 'NIE-001', 'send' => true], // el cliente intenta impone send
        ]));

        Http::fake([
            self::BASE.'/api/ubl2.1/payroll/102/set123' => Http::response([
                'success' => true,
                'data' => ['cune' => 'cune-1', 'cufe' => 'cune-1'],
            ], 200),
        ]);

        $emitido = $this->servicio()->emitir($documento->uuid);

        $this->assertSame('VALIDADA_SIN_ENVIO', $emitido->electronic_status);
        $this->assertSame('cune-1', $emitido->document_key);

        Http::assertSent(function ($peticion) {
            return str_contains($peticion->url(), '/api/ubl2.1/payroll/102/set123')
                && $peticion['send'] === false; // la bandera la pone el proveedor, no el cliente
        });
    }

    public function test_emitir_dos_veces_esta_prohibido(): void
    {
        $documento = $this->servicio()->inscribir($this->datosDocumento());
        Http::fake([
            self::BASE.'/api/ubl2.1/support-document/set123' => Http::response([
                'success' => true,
                'data' => ['cude' => 'cude-1'],
            ], 200),
        ]);
        $this->servicio()->emitir($documento->uuid);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede volver a emitir');
        $this->servicio()->emitir($documento->uuid);
    }

    public function test_editar_mientras_preciada_y_borrar_tras_respuesta_seguro(): void
    {
        $documento = $this->servicio()->inscribir($this->datosDocumento());

        // Edición antes de firmar: se permite y solo toca campos del documento.
        $editado = $this->servicio()->update($documento->uuid, [
            'document_number' => 'DS2002',
            'electronic_status' => 'ACEPTADA_DIAN', // el cliente intenta colar estado
        ]);
        $this->assertTrue($editado);
        $this->assertSame('PENDIENTE_DIAN', $documento->refresh()->electronic_status);
        $this->assertSame('DS2002', $documento->document_number);

        // Tras emitir ya no se edita ni se borra.
        Http::fake([
            self::BASE.'/api/ubl2.1/support-document/set123' => Http::response([
                'success' => true,
                'data' => ['cude' => 'cude-2'],
            ], 200),
        ]);
        $this->servicio()->emitir($documento->uuid);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no se puede eliminar');
        $this->servicio()->delete($documento->uuid);
    }

    public function test_estado_consulta_la_clave_en_api_dian(): void
    {
        $documento = $this->servicio()->inscribir($this->datosDocumento());

        // Sin clave todavía: respuesta local, sin llamadas HTTP.
        $estadoLocal = $this->servicio()->estado($documento->uuid);
        $this->assertSame('PENDIENTE_DIAN', $estadoLocal['estado']);
        Http::assertNothingSent();

        Http::fake([
            self::BASE.'/api/ubl2.1/status/document/cude-5' => Http::response([
                'success' => true,
                'data' => ['IsValid' => true],
            ], 200),
        ]);
        $documento->withoutLogging(fn () => $documento->update([
            'document_key' => 'cude-5',
            'billing_provider_uuid' => null, // se re-resuelve el proveedor activo
        ]));

        $estado = $this->servicio()->estado($documento->uuid);
        $this->assertSame('ACEPTADA_DIAN', $estado['estado']);
    }
}
