<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Contratos de alquiler de vehículos: aislamiento por empresa del vehículo
 * en la creación y generación automática de cuotas.
 */
class RentalContractTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresaA;

    private string $empresaB;

    private string $vehiculoA;

    private string $vehiculoB;

    private string $ownerA;

    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);

        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
        $this->vehiculoA = $this->insertar('vehicles', ['company_uuid' => $this->empresaA, 'vehicle_license_plate' => 'ALQ001', 'is_active' => 1]);
        $this->vehiculoB = $this->insertar('vehicles', ['company_uuid' => $this->empresaB, 'vehicle_license_plate' => 'ALQ002', 'is_active' => 1]);
        $this->ownerA = $this->insertar('owners', ['owner_name' => 'Dueño A', 'document_number' => '9000001']);
        $this->adminA = $this->crearUsuario($this->empresaA, 'ADMIN_EMPRESA', 'admin.alquiler.a@test.local');
    }

    public function test_rechaza_vehiculo_de_otra_empresa(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoB,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_fee' => 5000,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'vehicle_uuid', 'messages' => ['El vehículo no pertenece a la empresa o sede activa.']]);

        $this->assertSame(0, DB::table('rental_contracts')->count());
    }

    public function test_crea_contrato_y_genera_cuotas(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 3,
                'monthly_fee' => 5000,
            ])
            ->assertStatus(201);

        $this->assertSame(1, DB::table('rental_contracts')->count());
        $this->assertSame(3, DB::table('rental_installments')->count());
        $this->assertSame('active', DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('status'));
    }

    public function test_crea_contrato_obteniendo_propietario_desde_vehiculo(): void
    {
        $ownerDelVehiculo = $this->insertar('owners', [
            'vehicle_uuid' => $this->vehiculoA,
            'owner_name' => 'Propietario Vehículo A',
            'document_number' => '9000002',
        ]);

        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4500,
            ])
            ->assertStatus(201);

        $this->assertSame(
            $ownerDelVehiculo,
            DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('owner_uuid')
        );
    }

    public function test_rechaza_creacion_sin_owner_si_vehiculo_no_tiene_propietario(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'start_date' => '2026-12-01',
                'monthly_fee' => 4500,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'vehicle_uuid', 'messages' => ['El vehículo seleccionado no tiene un propietario registrado.']]);

        $this->assertSame(0, DB::table('rental_contracts')->count());
    }

    public function test_crea_contrato_creando_propietario_desde_tercero_del_vehiculo(): void
    {
        $docTypeUuid = $this->insertar('type_of_documents', []);

        $terceroUuid = $this->insertar('third_parties', [
            'company_uuid' => $this->empresaA,
            'document_type_uuid' => $docTypeUuid,
            'first_name' => 'Carlos',
            'last_name' => 'Perez',
            'document_number' => '80123456',
        ]);

        DB::table('vehicles')->where('uuid', $this->vehiculoA)->update(['third_party_uuid' => $terceroUuid]);

        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4500,
            ])
            ->assertStatus(201);

        $ownerUuid = DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('owner_uuid');
        $this->assertNotNull($ownerUuid);
        $this->assertSame($terceroUuid, DB::table('owners')->where('uuid', $ownerUuid)->value('third_party_uuid'));
    }

    public function test_registra_pago_en_cuota_y_recalcula_siguientes(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4200000,
            ])
            ->assertStatus(201);

        $cuota1Uuid = DB::table('rental_installments')
            ->where('installment_number', 1)
            ->value('uuid');

        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'paid_amount' => 4200000,
                'payment_date' => '2026-11-19',
                'discount_amount' => 0,
                'notes' => null,
            ])
            ->assertStatus(200);

        $cuota1 = DB::table('rental_installments')->where('uuid', $cuota1Uuid)->first();
        $cuota2 = DB::table('rental_installments')->where('installment_number', 2)->first();

        $this->assertSame('paid', $cuota1->status);
        $this->assertEquals(0.0, (float) $cuota1->pending_balance);
        $this->assertEquals(0.0, (float) $cuota2->previous_balance);
        $this->assertEquals(4200000.0, (float) $cuota2->net_amount);
    }

    public function test_descarga_pdf_de_contrato(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4200000,
            ])
            ->assertStatus(201);

        $contractUuid = DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('uuid');

        $response = $this->como($this->adminA, $this->empresaA)
            ->get("/api/v1/fleet-management/rental-contracts/{$contractUuid}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_genera_numero_de_contrato_secuencial_de_4_digitos_por_empresa(): void
    {
        $vehiculoA2 = $this->insertar('vehicles', [
            'company_uuid' => $this->empresaA,
            'vehicle_license_plate' => 'ALQ003',
            'is_active' => 1,
        ]);

        $res1 = $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4000,
            ])
            ->assertStatus(201);

        $res2 = $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $vehiculoA2,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4500,
            ])
            ->assertStatus(201);

        $this->assertSame('0001', $res1->json('data.contract_number'));
        $this->assertSame('0002', $res2->json('data.contract_number'));
    }

    public function test_abono_parcial_cierra_cuota_traslada_saldo_y_pago_completo_siguiente_paga_ambas(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 3,
                'monthly_fee' => 4800000,
            ])
            ->assertStatus(201);

        $cuota1Uuid = DB::table('rental_installments')->where('installment_number', 1)->value('uuid');
        $cuota2Uuid = DB::table('rental_installments')->where('installment_number', 2)->value('uuid');

        // 1) Abono parcial de 4.2M en cuota 1: queda cerrada en partial, 600k pasan SOLO a cuota 2 (cuota 3 sigue en 4.8M)
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'payment_amount' => 4200000,
                'payment_date' => '2026-12-05',
                'discount_amount' => 0,
            ])
            ->assertStatus(200);

        $cuota1 = DB::table('rental_installments')->where('uuid', $cuota1Uuid)->first();
        $cuota2 = DB::table('rental_installments')->where('uuid', $cuota2Uuid)->first();
        $cuota3 = DB::table('rental_installments')->where('installment_number', 3)->first();

        $this->assertSame('partial', $cuota1->status);
        $this->assertEquals(600000.0, (float) $cuota1->pending_balance);
        $this->assertEquals(600000.0, (float) $cuota2->previous_balance);
        $this->assertEquals(5400000.0, (float) $cuota2->net_amount);
        $this->assertEquals(0.0, (float) $cuota3->previous_balance);
        $this->assertEquals(4800000.0, (float) $cuota3->net_amount);

        // 2) Intentar volver a pagar en cuota 1 (ya cerrada en partial) es rechazado
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'payment_amount' => 600000,
            ])
            ->assertStatus(400);

        // 3) Pagar completa la cuota 2 (5.4M): tanto cuota 2 como cuota 1 cambian a paid
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota2Uuid}", [
                'payment_amount' => 5400000,
                'payment_date' => '2026-12-10',
                'discount_amount' => 0,
            ])
            ->assertStatus(200);

        $cuota1 = DB::table('rental_installments')->where('uuid', $cuota1Uuid)->first();
        $cuota2 = DB::table('rental_installments')->where('uuid', $cuota2Uuid)->first();

        $this->assertSame('paid', $cuota2->status);
        $this->assertEquals(0.0, (float) $cuota2->pending_balance);
        $this->assertSame('paid', $cuota1->status);
        $this->assertEquals(0.0, (float) $cuota1->pending_balance);
        $this->assertEquals(4200000.0, (float) $cuota1->paid_amount);
        $this->assertEquals(5400000.0, (float) $cuota2->paid_amount);
    }

    public function test_registra_y_elimina_descuento_solo_en_cuota_vigente(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 3,
                'monthly_fee' => 4800000,
            ])
            ->assertStatus(201);

        $contractUuid = DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('uuid');
        $cuota1Uuid = DB::table('rental_installments')->where('installment_number', 1)->value('uuid');

        // Pagar cuota 1 completa -> la cuota vigente pasa a ser la #2
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'payment_amount' => 4800000,
                'payment_date' => '2026-12-05',
            ])
            ->assertStatus(200);

        // Registrar descuento en el contrato -> debe aplicarse SOLO a la cuota vigente (#2)
        $resDesc = $this->como($this->adminA, $this->empresaA)
            ->postJson("/api/v1/fleet-management/rental-contracts/{$contractUuid}/discounts", [
                'discount_date' => '2026-12-15',
                'concept' => 'Reparación llanta',
                'amount' => 300000,
                'observations' => 'Factura #123',
            ])
            ->assertStatus(201);

        $discountUuid = $resDesc->json('data.uuid');
        $cuota2 = DB::table('rental_installments')->where('installment_number', 2)->first();
        $cuota3 = DB::table('rental_installments')->where('installment_number', 3)->first();

        $this->assertEquals(300000.0, (float) $cuota2->discount_amount);
        $this->assertEquals(4500000.0, (float) $cuota2->net_amount);
        $this->assertEquals(4500000.0, (float) $cuota2->pending_balance);
        $this->assertEquals(0.0, (float) $cuota3->discount_amount);
        $this->assertEquals(4800000.0, (float) $cuota3->net_amount);

        // Eliminar el descuento -> restaura el canon de la cuota #2 a 4.8M
        $this->como($this->adminA, $this->empresaA)
            ->deleteJson("/api/v1/fleet-management/rental-contracts/discounts/{$discountUuid}")
            ->assertStatus(200);

        $cuota2 = DB::table('rental_installments')->where('installment_number', 2)->first();
        $this->assertEquals(0.0, (float) $cuota2->discount_amount);
        $this->assertEquals(4800000.0, (float) $cuota2->net_amount);
    }

    public function test_listado_devuelve_totales_agregados_por_contrato(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 3,
                'monthly_fee' => 1000,
            ])
            ->assertStatus(201);

        $contractUuid = DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('uuid');
        $cuota1Uuid = DB::table('rental_installments')->where('installment_number', 1)->value('uuid');

        $this->como($this->adminA, $this->empresaA)
            ->postJson("/api/v1/fleet-management/rental-contracts/{$contractUuid}/discounts", [
                'discount_date' => '2026-12-02',
                'concept' => 'Ajuste',
                'amount' => 200,
            ])
            ->assertStatus(201);

        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'payment_amount' => 800,
                'payment_date' => '2026-12-05',
            ])
            ->assertStatus(200);

        $res = $this->como($this->adminA, $this->empresaA)
            ->getJson('/api/v1/fleet-management/rental-contracts')
            ->assertStatus(200);

        $row = $res->json('data.data.0');
        $this->assertEquals(3000.0, (float) $row['total_billed']);
        $this->assertEquals(200.0, (float) $row['total_discounts']);
        $this->assertEquals(800.0, (float) $row['total_paid']);
    }

    public function test_saldo_a_favor_se_aplica_como_abono_adelantado_a_cuota_siguiente(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 3,
                'monthly_fee' => 4800000,
            ])
            ->assertStatus(201);

        $cuota1Uuid = DB::table('rental_installments')->where('installment_number', 1)->value('uuid');
        $cuota2Uuid = DB::table('rental_installments')->where('installment_number', 2)->value('uuid');

        // Pagar 5.250.000 en cuota 1 (de 4.800.000) -> 450.000 a favor se aplican a cuota 2
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'payment_amount' => 5250000,
                'payment_date' => '2026-12-05',
            ])
            ->assertStatus(200);

        $cuota1 = DB::table('rental_installments')->where('uuid', $cuota1Uuid)->first();
        $cuota2 = DB::table('rental_installments')->where('uuid', $cuota2Uuid)->first();
        $cuota3 = DB::table('rental_installments')->where('installment_number', 3)->first();

        $this->assertSame('paid', $cuota1->status);
        $this->assertEquals(4800000.0, (float) $cuota1->paid_amount);
        $this->assertSame('partial', $cuota2->status);
        $this->assertEquals(450000.0, (float) $cuota2->paid_amount);
        $this->assertEquals(4350000.0, (float) $cuota2->pending_balance);
        $this->assertEquals(0.0, (float) $cuota3->previous_balance);

        // Cuota 2 sigue abierta: puede pagar el resto (4.350.000) y quedar paid
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota2Uuid}", [
                'payment_amount' => 4350000,
                'payment_date' => '2027-01-05',
            ])
            ->assertStatus(200);

        $cuota2 = DB::table('rental_installments')->where('uuid', $cuota2Uuid)->first();
        $this->assertSame('paid', $cuota2->status);
        $this->assertEquals(4800000.0, (float) $cuota2->paid_amount);
        $this->assertEquals(0.0, (float) $cuota2->pending_balance);
    }

    public function test_no_cierra_cuota_parcial_anterior_si_saldo_no_fue_trasladado(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 4800000,
            ])
            ->assertStatus(201);

        $cuota1Uuid = DB::table('rental_installments')->where('installment_number', 1)->value('uuid');
        $cuota2Uuid = DB::table('rental_installments')->where('installment_number', 2)->value('uuid');

        // Simular cuota 1 parcial sin traslado a cuota 2 (previous_balance = 0 en cuota 2)
        DB::table('rental_installments')->where('uuid', $cuota1Uuid)->update([
            'paid_amount' => 4700000,
            'pending_balance' => 100000,
            'status' => 'partial',
        ]);

        // Pagar cuota 2 por su canon normal (4.8M, sin incluir los 100k de cuota 1)
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota2Uuid}", [
                'payment_amount' => 4800000,
                'payment_date' => '2027-01-05',
            ])
            ->assertStatus(200);

        $cuota1 = DB::table('rental_installments')->where('uuid', $cuota1Uuid)->first();
        $this->assertSame('partial', $cuota1->status);
        $this->assertEquals(100000.0, (float) $cuota1->pending_balance);
    }

    public function test_cierra_contrato_automaticamente_al_pagar_todas_las_cuotas(): void
    {
        $this->como($this->adminA, $this->empresaA)
            ->postJson('/api/v1/fleet-management/rental-contracts', [
                'vehicle_uuid' => $this->vehiculoA,
                'owner_uuid' => $this->ownerA,
                'start_date' => '2026-12-01',
                'monthly_term' => 2,
                'monthly_fee' => 1000,
            ])
            ->assertStatus(201);

        $contractUuid = DB::table('rental_contracts')->where('vehicle_uuid', $this->vehiculoA)->value('uuid');
        $cuota1Uuid = DB::table('rental_installments')->where('installment_number', 1)->value('uuid');
        $cuota2Uuid = DB::table('rental_installments')->where('installment_number', 2)->value('uuid');

        // Descuento de 100 en cuota 1 + pago de 900 -> cuota 1 pagada, contrato aún activo
        $this->como($this->adminA, $this->empresaA)
            ->postJson("/api/v1/fleet-management/rental-contracts/{$contractUuid}/discounts", [
                'discount_date' => '2026-12-02',
                'concept' => 'Descuento prueba',
                'amount' => 100,
            ])
            ->assertStatus(201);

        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota1Uuid}", [
                'payment_amount' => 900,
                'payment_date' => '2026-12-05',
            ])
            ->assertStatus(200);

        $this->assertSame('active', DB::table('rental_contracts')->where('uuid', $contractUuid)->value('status'));

        // Pago completo de cuota 2 -> todas las cuotas pagadas -> contrato pasa a finished
        $this->como($this->adminA, $this->empresaA)
            ->putJson("/api/v1/fleet-management/rental-installments/{$cuota2Uuid}", [
                'payment_amount' => 1000,
                'payment_date' => '2027-01-05',
            ])
            ->assertStatus(200);

        $this->assertSame('finished', DB::table('rental_contracts')->where('uuid', $contractUuid)->value('status'));
    }

    private function como(User $usuario, string $empresa)
    {
        Sanctum::actingAs($usuario, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $empresa]);
    }

    private function crearEmpresa(): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.Str::random(5), 'municipality_uuid' => $relleno, 'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno, 'web_page' => 'https://e.test',
            'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function crearUsuario(string $empresa, string $rol, string $email): User
    {
        $rolInstancia = Role::findOrCreate($rol, 'api');
        $rolInstancia->givePermissionTo([
            Permission::findOrCreate('rental_contracts.index', 'api'),
            Permission::findOrCreate('rental_contracts.create', 'api'),
            Permission::findOrCreate('rental_contracts.show', 'api'),
            Permission::findOrCreate('rental_installments.update', 'api'),
        ]);

        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        $user->assignRole($rolInstancia);
        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }
}
