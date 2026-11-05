<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Fuec;
use App\Models\User;
use App\Services\ContractExtraction\FuecService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Pruebas de la funcionalidad y autorización de renovación de FUEC.
 *
 * Renovar genera un nuevo FUEC para el mismo contrato/contratante:
 * - El número de contrato es idéntico.
 * - El consecutivo FUEC se incrementa.
 * - El FUEC anterior permanece intacto.
 * - Solo está permitido para SUPERADMIN y ADMIN_EMPRESA (permiso fuec.renew).
 */
class RenovarFuecTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;
    private string $vehiculo;
    private string $conductor;
    private string $objeto;
    private string $tipoDocumento;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);

        $this->empresa = $this->insertar('companies', ['business_name' => 'Transportes Renovación SAS', 'is_active' => true]);
        request()->attributes->set('current_company_uuid', $this->empresa);

        $this->insertar('system_configuration', [
            'company_uuid' => $this->empresa,
            'fuec_use_corporate_policies' => false,
            'fuec_require_daily_inspections' => false,
            'fuec_require_social_security' => false,
        ]);

        $this->vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'RNV123',
            'registration_date' => '2020-05-01',
            'type_of_service' => 'PUBLICO',
            'is_active' => true,
        ]);

        $this->conductor = $this->insertar('third_parties', ['company_uuid' => $this->empresa]);
        $this->objeto = $this->insertar('objects_contracts', []);
        $this->tipoDocumento = $this->insertar('type_of_documents', []);

        $this->insertar('enabling_resolutions', [
            'company_uuid' => $this->empresa,
            'status' => 1,
            'territorial_code' => '11001',
            'resolution_number' => '5678',
            'resolution_date' => '2026-01-15',
        ]);

        // Pólizas y documentos vivos para el vehículo
        $this->insertarDocumento('RCC', '2027-12-31');
        $this->insertarDocumento('RCE', '2027-12-31');
        $this->insertarDocumento('SOAT', '2027-12-31');
        $this->insertarDocumento('RTM', '2027-12-31');
    }

    private function insertarDocumento(string $tipo, string $vence): void
    {
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'document_type' => $tipo,
            'policy_number' => "POL-{$tipo}-01",
            'issue_date' => '2025-01-01',
            'expiry_date' => $vence,
            'status' => 'VIGENTE',
        ]);
    }

    public function test_permiso_fuec_renew_esta_asignado_exclusivamente_a_superadmin_y_admin_empresa(): void
    {
        $permiso = Permission::where('name', 'fuec.renew')->where('guard_name', 'api')->first();
        $this->assertNotNull($permiso, 'El permiso fuec.renew no fue creado por la migración');

        $superAdmin = Role::where('name', 'SUPERADMIN')->where('guard_name', 'api')->first();
        $adminEmpresa = Role::where('name', 'ADMIN_EMPRESA')->where('guard_name', 'api')->first();
        $empleado = Role::where('name', 'EMPLEADO')->where('guard_name', 'api')->first();
        $afiliado = Role::where('name', 'AFILIADO')->where('guard_name', 'api')->first();

        if ($superAdmin) {
            $this->assertTrue($superAdmin->hasPermissionTo('fuec.renew'));
        }
        if ($adminEmpresa) {
            $this->assertTrue($adminEmpresa->hasPermissionTo('fuec.renew'));
        }
        if ($empleado) {
            $this->assertFalse($empleado->hasPermissionTo('fuec.renew'));
        }
        if ($afiliado) {
            $this->assertFalse($afiliado->hasPermissionTo('fuec.renew'));
        }
    }

    public function test_renovar_fuec_crea_nuevo_registro_con_mismo_contrato_y_consecutivo_aumentado(): void
    {
        $service = app(FuecService::class);

        // 1. Crear el FUEC inicial
        $datosPrimerFuec = [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'issue_date' => '2026-11-01',
            'effective_date' => '2026-11-01',
            'expiration_date' => '2026-11-05',
            'origin_route' => 'BOGOTÁ',
            'destination_route' => 'CALI',
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'contractor' => [
                'document_type_uuid' => $this->tipoDocumento,
                'document_number' => '900999888',
                'company_name' => 'Empresa Contratante Original',
                'address' => 'Calle 10 #20-30',
                'telephone' => '3109998877',
                'contract_number' => '0001',
                'contracting_party_city' => 'Bogotá',
                'vehicle_uuid' => $this->vehiculo,
                'responsible_name' => 'Gerente Contratante',
                'responsible_document' => '79111222',
                'responsible_phone' => '3109998877',
                'responsible_address' => 'Calle 10 #20-30',
            ],
            'passengers' => [
                [
                    'type_of_document_uuid' => $this->tipoDocumento,
                    'document_number' => '102030',
                    'first_and_last_name' => 'Pasajero Uno',
                ],
            ],
        ];

        $primerFuec = $service->createFuec($datosPrimerFuec);
        $this->assertNotNull($primerFuec->uuid);
        $this->assertEquals('0001', $primerFuec->number_fuec);
        $this->assertStringEndsWith('00010001', $primerFuec->request_number);
        $contractorUuid = $primerFuec->contractor_uuid;
        $this->assertNotNull($contractorUuid);

        // 2. Comprobar vista previa del número FUEC para la renovación
        $previewNum = $service->getPreviewFuecNumber($this->empresa, $contractorUuid);
        $this->assertNotEmpty($previewNum);
        $this->assertStringEndsWith('00010002', $previewNum, 'La vista previa no incrementó el consecutivo de extracto');

        // 3. Renovar: Crear nuevo FUEC conservando los datos del contrato original pero con nuevas fechas
        $datosFuecRenovado = [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'issue_date' => '2026-11-06',
            'effective_date' => '2026-11-06',
            'expiration_date' => '2026-11-10',
            'origin_route' => 'BOGOTÁ',
            'destination_route' => 'CALI',
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'contractor' => [
                'uuid' => $contractorUuid,
                'document_type_uuid' => $this->tipoDocumento,
                'document_number' => '900999888',
                'company_name' => 'Empresa Contratante Original',
                'address' => 'Calle 10 #20-30',
                'telephone' => '3109998877',
                'contract_number' => '0001',
                'contracting_party_city' => 'Bogotá',
                'vehicle_uuid' => $this->vehiculo,
                'responsible_name' => 'Gerente Contratante',
                'responsible_document' => '79111222',
                'responsible_phone' => '3109998877',
                'responsible_address' => 'Calle 10 #20-30',
            ],
            'passengers' => [
                [
                    'type_of_document_uuid' => $this->tipoDocumento,
                    'document_number' => '102030',
                    'first_and_last_name' => 'Pasajero Uno',
                ],
            ],
        ];

        $fuecRenovado = $service->createFuec($datosFuecRenovado);

        // A) El FUEC renovado es un nuevo registro
        $this->assertNotEquals($primerFuec->uuid, $fuecRenovado->uuid, 'El FUEC renovado debe tener un UUID nuevo');

        // B) El número de contrato es exactamente el mismo
        $this->assertEquals($primerFuec->contract_number_display, $fuecRenovado->contract_number_display);
        $this->assertEquals('0001', $fuecRenovado->contract_number_display);

        // C) El consecutivo FUEC aumentó a 0002
        $this->assertEquals('0002', $fuecRenovado->number_fuec, 'El consecutivo FUEC debe haber aumentado a 0002');
        $this->assertStringEndsWith('00010002', $fuecRenovado->request_number, 'El número completo de FUEC debe terminar en 00010002');

        // D) El FUEC anterior original sigue intacto
        $primerFuecRecargado = Fuec::where('uuid', $primerFuec->uuid)->first();
        $this->assertNotNull($primerFuecRecargado);
        $this->assertEquals('0001', $primerFuecRecargado->number_fuec);
        $this->assertEquals('2026-11-01', substr((string) $primerFuecRecargado->effective_date, 0, 10));
        $this->assertEquals('2026-11-05', substr((string) $primerFuecRecargado->expiration_date, 0, 10));
    }

    public function test_usuario_sin_permiso_fuec_renew_recibe_403_al_intentar_renovar(): void
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empleado Sin Permiso',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
        ]);

        setPermissionsTeamId(null);
        $roleEmpleado = Role::firstOrCreate(['name' => 'EMPLEADO', 'guard_name' => 'api']);
        $user->assignRole($roleEmpleado);

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'third_party_uuid' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $payload = [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'issue_date' => '2026-11-06',
            'effective_date' => '2026-11-06',
            'expiration_date' => '2026-11-10',
            'origin_route' => 'BOGOTÁ',
            'destination_route' => 'CALI',
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'contract_number_display' => '0001',
            'renewed_from_uuid' => (string) Str::uuid(),
            'contractor' => [
                'uuid' => (string) Str::uuid(),
                'document_type_uuid' => $this->tipoDocumento,
                'document_number' => '900999888',
                'company_name' => 'Empresa Contratante',
                'address' => 'Calle 10 #20-30',
                'telephone' => '3109998877',
                'contract_number' => '0001',
                'contracting_party_city' => 'Bogotá',
                'vehicle_uuid' => $this->vehiculo,
                'responsible_name' => 'Gerente Contratante',
                'responsible_document' => '79111222',
                'responsible_phone' => '3109998877',
                'responsible_address' => 'Calle 10 #20-30',
            ],
        ];

        $response = $this->postJson('/api/v1/contract-extract/fuecs', $payload, [
            'X-Company-UUID' => $this->empresa,
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Acceso no autorizado', $response->json('message') ?? '');
    }

    public function test_admin_empresa_puede_renovar_via_endpoint(): void
    {
        $service = app(FuecService::class);

        // 1. Crear FUEC base
        $datosPrimerFuec = [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'issue_date' => '2026-11-01',
            'effective_date' => '2026-11-01',
            'expiration_date' => '2026-11-05',
            'origin_route' => 'BOGOTÁ',
            'destination_route' => 'CALI',
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'renewed_from_uuid' => (string) Str::uuid(),
            'contractor' => [
                'document_type_uuid' => $this->tipoDocumento,
                'document_number' => '900999888',
                'company_name' => 'Empresa Contratante Original',
                'address' => 'Calle 10 #20-30',
                'telephone' => '3109998877',
                'contract_number' => '0001',
                'contracting_party_city' => 'Bogotá',
                'vehicle_uuid' => $this->vehiculo,
                'responsible_name' => 'Gerente Contratante',
                'responsible_document' => '79111222',
                'responsible_phone' => '3109998877',
                'responsible_address' => 'Calle 10 #20-30',
            ],
        ];
        $primerFuec = $service->createFuec($datosPrimerFuec);

        // 2. Crear usuario ADMIN_EMPRESA
        $userAdmin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Empresa',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
        ]);

        setPermissionsTeamId(null);
        $roleAdmin = Role::firstOrCreate(['name' => 'ADMIN_EMPRESA', 'guard_name' => 'api']);
        $userAdmin->assignRole($roleAdmin);

        DB::table('company_user')->insert([
            'user_id' => $userAdmin->id,
            'company_uuid' => $this->empresa,
            'third_party_uuid' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($userAdmin, ['*']);

        $payload = [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'issue_date' => '2026-11-06',
            'effective_date' => '2026-11-06',
            'expiration_date' => '2026-11-10',
            'origin_route' => 'BOGOTÁ',
            'destination_route' => 'CALI',
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'contract_number_display' => '0001',
            'contractor' => [
                'uuid' => $primerFuec->contractor_uuid,
                'document_type_uuid' => $this->tipoDocumento,
                'document_number' => '900999888',
                'company_name' => 'Empresa Contratante Original',
                'address' => 'Calle 10 #20-30',
                'telephone' => '3109998877',
                'contract_number' => '0001',
                'contracting_party_city' => 'Bogotá',
                'vehicle_uuid' => $this->vehiculo,
                'responsible_name' => 'Gerente Contratante',
                'responsible_document' => '79111222',
                'responsible_phone' => '3109998877',
                'responsible_address' => 'Calle 10 #20-30',
            ],
        ];

        $response = $this->postJson('/api/v1/contract-extract/fuecs', $payload, [
            'X-Company-UUID' => $this->empresa,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('0002', $response->json('data.number_fuec'));
        $this->assertEquals('0001', $response->json('data.contract_number_display'));
    }
}
