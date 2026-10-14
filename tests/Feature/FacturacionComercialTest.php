<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Billing\CommercialInvoiceService;
use App\Services\Billing\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * Módulo de facturación comercial: cotización (fase 1) y factura interna (fase 2).
 *
 * La factura interna queda en PENDIENTE_DIAN: se genera completa (incluido el
 * UBL guardado) pero nada se transmite al proveedor tecnológico.
 */
class FacturacionComercialTest extends TestCase
{
    use InsertaFilas, PresupuestoConsultas, RefreshDatabase;

    private string $empresa;

    private string $cliente;

    private string $resolucion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Facturación Test']);
        $this->cliente = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'JURIDICA',
            'document_number' => '900123456',
            'email' => 'cliente@test.local',
            'municipality_uuid' => (string) Str::uuid(),
            'tax_regime' => '49',
            'tax_responsibility_uuid' => (string) Str::uuid(),
            'document_type_uuid' => (string) Str::uuid(),
            'is_customer' => 1,
            'is_active' => 1,
        ]);
        $this->insertar('billing_resolution_types', ['code' => '01', 'name' => 'Factura de venta', 'is_active' => 1]);
        $this->resolucion = $this->insertar('billing_resolutions', [
            'company_uuid' => $this->empresa,
            'resolution_type_code' => '01',
            'invoice_prefix' => 'TSB',
            'invoice_start_number' => 1,
            'invoice_end_number' => 9999,
            'last_consecutive' => 0,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'is_active' => 1,
        ]);
    }

    private function servicio(): CommercialInvoiceService
    {
        return app(CommercialInvoiceService::class);
    }

    /** @return array<string, mixed> */
    private function datosCotizacion(): array
    {
        return [
            'company_uuid' => $this->empresa,
            'issue_date' => '2026-10-01',
            'valid_until' => '2026-10-31',
            'third_party_uuid' => $this->cliente,
            'origin_route' => 'Sincelejo',
            'destination_route' => 'Montería',
            'items' => [
                [
                    'description' => 'Servicio transporte especial mensual',
                    'quantity' => 1,
                    'unit_price' => 4300000,
                    'tax_rate' => 0,
                ],
                [
                    'description' => 'Recargo nocturno',
                    'quantity' => 2,
                    'unit_price' => 100000,
                    'discount_amount' => 20000,
                    'tax_rate' => 0,
                    'withholdings' => [
                        ['withholding_type' => 'RETEFUENTE', 'withholding_rate' => 4],
                    ],
                ],
            ],
        ];
    }

    private function superadmin(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Admin', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('SUPERADMIN', 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $this->empresa, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_crear_cotizacion_calcula_totales(): void
    {
        $cotizacion = $this->servicio()->crearCotizacion($this->datosCotizacion());

        // Ítem 1: 4.300.000. Ítem 2: base 200.000 - 20.000 = 180.000, retefuente 4% = 7.200.
        $this->assertSame('COTIZACION', $cotizacion->invoice_type);
        $this->assertSame('BORRADOR', $cotizacion->status);
        $this->assertSame('COT-0001', $cotizacion->invoice_number);
        $this->assertEquals(4480000.0, (float) $cotizacion->subtotal);
        $this->assertEquals(20000.0, (float) $cotizacion->total_discount);
        $this->assertEquals(7200.0, (float) $cotizacion->total_withholding);
        $this->assertEquals(4472800.0, (float) $cotizacion->invoice_total);
        $this->assertCount(2, $cotizacion->items);
    }

    public function test_cotizacion_sin_cliente_o_sin_items_falla(): void
    {
        $this->expectException(\RuntimeException::class);

        $datos = $this->datosCotizacion();
        $datos['third_party_uuid'] = (string) Str::uuid();
        $this->servicio()->crearCotizacion($datos);
    }

    public function test_estados_de_cotizacion_siguen_orden(): void
    {
        $servicio = $this->servicio();
        $cotizacion = $servicio->crearCotizacion($this->datosCotizacion());

        $this->expectException(\RuntimeException::class);
        $servicio->cambiarEstado($cotizacion->uuid, 'APROBADA');

        $cotizacion = $servicio->cambiarEstado($cotizacion->uuid, 'ENVIADA');
        $this->assertSame('ENVIADA', $cotizacion->status);

        $cotizacion = $servicio->cambiarEstado($cotizacion->uuid, 'APROBADA');
        $this->assertSame('APROBADA', $cotizacion->status);
    }

    public function test_crear_factura_consume_consecutivo_y_queda_pendiente_dian(): void
    {
        $datos = $this->datosCotizacion();
        $datos['billing_resolution_uuid'] = $this->resolucion;
        $datos['due_date'] = '2026-11-01';
        unset($datos['valid_until']);

        $factura = $this->servicio()->crearFactura($datos);

        $this->assertSame('FACTURA_VENTA', $factura->invoice_type);
        $this->assertSame('TSB1', $factura->invoice_number);
        $this->assertSame('EMITIDA_INTERNA', $factura->status);
        $this->assertSame('PENDIENTE_DIAN', $factura->electronic_status);
        $this->assertNull($factura->cufe);
        $this->assertNotEmpty($factura->fresh()->ubl_document);
        $this->assertNotNull($factura->electronicInvoice);
        $this->assertSame(1, (int) DB::table('billing_resolutions')->where('uuid', $this->resolucion)->value('last_consecutive'));
    }

    public function test_no_se_factura_dos_veces_el_mismo_fuec(): void
    {
        $fuec = (string) Str::uuid();
        $datos = $this->datosCotizacion();
        $datos['billing_resolution_uuid'] = $this->resolucion;
        $datos['items'][0]['fuec_uuid'] = $fuec;

        $this->servicio()->crearFactura($datos);

        $this->expectException(\RuntimeException::class);
        $this->servicio()->crearFactura($datos);
    }

    public function test_convertir_cotizacion_aprobada_genera_factura(): void
    {
        $servicio = $this->servicio();
        $cotizacion = $servicio->crearCotizacion($this->datosCotizacion());
        $servicio->cambiarEstado($cotizacion->uuid, 'ENVIADA');
        $servicio->cambiarEstado($cotizacion->uuid, 'APROBADA');

        $factura = $servicio->convertirCotizacion($cotizacion->uuid, [
            'billing_resolution_uuid' => $this->resolucion,
            'issue_date' => '2026-10-05',
            'due_date' => '2026-11-05',
        ]);

        $this->assertSame('FACTURA_VENTA', $factura->invoice_type);
        $this->assertSame($cotizacion->uuid, $factura->origin_uuid);
        $this->assertEquals((float) $cotizacion->invoice_total, (float) $factura->invoice_total);
        $this->assertSame('CONVERTIDA', $servicio->getDetalle($cotizacion->uuid)->status);
    }

    public function test_registrar_pagos_actualiza_cartera(): void
    {
        $servicio = $this->servicio();
        $datos = $this->datosCotizacion();
        $datos['billing_resolution_uuid'] = $this->resolucion;
        $factura = $servicio->crearFactura($datos);
        $total = (float) $factura->invoice_total;

        $factura = $servicio->registrarPago($factura->uuid, ['amount' => 1000000, 'payment_date' => '2026-10-10']);
        $this->assertSame('PAGADA_PARCIAL', $factura->status);
        $this->assertEquals(1000000.0, (float) $factura->amount_paid);

        $factura = $servicio->registrarPago($factura->uuid, ['amount' => $total - 1000000, 'payment_date' => '2026-10-11']);
        $this->assertSame('PAGADA', $factura->status);
    }

    public function test_nota_credito_emitida_reduce_total_factura(): void
    {
        $servicio = $this->servicio();
        $datos = $this->datosCotizacion();
        $datos['billing_resolution_uuid'] = $this->resolucion;
        $factura = $servicio->crearFactura($datos);
        $total = (float) $factura->invoice_total;

        $notas = app(CreditNoteService::class);
        $nota = $notas->crearNota([
            'invoice_uuid' => $factura->uuid,
            'correction_concept_code' => '01',
            'observation' => 'Descuento comercial',
            'issue_date' => '2026-10-12',
            'items' => [['description' => 'Ajuste', 'quantity' => 1, 'unit_price' => 100000]],
        ]);
        $this->assertSame('BORRADOR', $nota->status);

        $notas->emitirNota($nota->uuid);
        $this->assertEquals($total - 100000, (float) $factura->fresh()->invoice_total);
    }

    public function test_afiliado_solo_ve_sus_documentos(): void
    {
        $otro = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'person_type' => 'JURIDICA',
            'document_number' => '900999888',
            'email' => 'otro@test.local',
            'municipality_uuid' => (string) Str::uuid(),
            'tax_regime' => '49',
            'tax_responsibility_uuid' => (string) Str::uuid(),
            'document_type_uuid' => (string) Str::uuid(),
            'is_customer' => 1,
            'is_affiliate' => 1,
            'is_active' => 1,
        ]);

        $servicio = $this->servicio();
        $propia = $servicio->crearCotizacion($this->datosCotizacion());
        $datosAjenos = $this->datosCotizacion();
        $datosAjenos['third_party_uuid'] = $otro;
        $ajena = $servicio->crearCotizacion($datosAjenos);

        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Afiliado', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('AFILIADO', 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $this->empresa,
            'third_party_uuid' => $this->cliente, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($user->fresh(), ['*']);
        request()->attributes->set('current_company_uuid', $this->empresa);
        request()->attributes->set('current_third_party_uuid', $this->cliente);

        $this->assertNotNull($servicio->getDetalle($propia->uuid));
        $this->assertNull($servicio->getDetalle($ajena->uuid));
    }

    public function test_cotizacion_sin_empresa_usa_la_del_contexto(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        $datos = $this->datosCotizacion();
        unset($datos['company_uuid']);

        $respuesta = $this->postJson('/api/v1/billing/commercial-invoices/quotations', $datos, ['X-Company-UUID' => $this->empresa]);
        $respuesta->assertCreated();
        $this->assertSame($this->empresa, $respuesta->json('data.company_uuid'));
    }

    public function test_http_crea_cotizacion_y_factura(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        $respuesta = $this->postJson('/api/v1/billing/commercial-invoices/quotations', $this->datosCotizacion(), ['X-Company-UUID' => $this->empresa]);
        $respuesta->assertCreated();
        $uuid = $respuesta->json('data.uuid');
        $this->assertNotEmpty($uuid);

        $this->patchJson("/api/v1/billing/commercial-invoices/{$uuid}/estado", ['status' => 'ENVIADA'], ['X-Company-UUID' => $this->empresa])->assertOk();
        $this->patchJson("/api/v1/billing/commercial-invoices/{$uuid}/estado", ['status' => 'APROBADA'], ['X-Company-UUID' => $this->empresa])->assertOk();

        $conversion = $this->postJson("/api/v1/billing/commercial-invoices/{$uuid}/convertir", [
            'billing_resolution_uuid' => $this->resolucion,
            'issue_date' => '2026-10-05',
            'due_date' => '2026-11-05',
        ], ['X-Company-UUID' => $this->empresa]);
        $conversion->assertCreated();
        $this->assertSame('PENDIENTE_DIAN', $conversion->json('data.electronic_status'));

        $facturaUuid = $conversion->json('data.uuid');
        $this->getJson("/api/v1/billing/commercial-invoices/{$facturaUuid}/pdf", ['X-Company-UUID' => $this->empresa])->assertOk();
    }

    #[Group('perf')]
    public function test_el_listado_tiene_costo_acotado_y_constante(): void
    {
        $servicio = $this->servicio();
        $servicio->crearCotizacion($this->datosCotizacion());

        $medir = function () use ($servicio) {
            $pagina = $servicio->search(['company_uuid' => $this->empresa], ['*'], 15, ['customer']);
            $this->assertMedicionConDatos($pagina->total(), 'listado de documentos');

            return $pagina;
        };

        $medir();
        $this->assertPresupuesto(15, $medir, 'listado de documentos');

        $ampliar = function () use ($servicio) {
            for ($i = 0; $i < 4; $i++) {
                $servicio->crearCotizacion($this->datosCotizacion());
            }
        };

        [$pocos, $muchos] = $this->assertConteoConstante($medir, $ampliar, 'listado de documentos');
        $this->assertSame($pocos, $muchos);
    }
}
