<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ContractExtraction\FuecService;
use App\Services\Fleet\VehicleDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * La RCC/RCE reemplazada (historial) no debe bloquear el FUEC ni
 * contaminar el listado: solo cuenta la póliza viva más reciente.
 */
class FuecPolizaVigenteTest extends TestCase
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
        $this->empresa = $this->insertar('companies', ['business_name' => 'FUEC póliza vigente', 'is_active' => true]);
        $this->insertar('system_configuration', [
            'company_uuid' => $this->empresa,
            'fuec_use_corporate_policies' => false,
            'fuec_require_daily_inspections' => false,
            'fuec_require_social_security' => false,
        ]);
        $this->vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'RCC001',
            'registration_date' => '2020-05-01',
        ]);
        $this->conductor = $this->insertar('third_parties', ['company_uuid' => $this->empresa]);
        $this->objeto = $this->insertar('objects_contracts', []);
        $this->tipoDocumento = $this->insertar('type_of_documents', []);
        $this->insertar('enabling_resolutions', [
            'company_uuid' => $this->empresa,
            'status' => 1,
            'territorial_code' => '11001',
            'resolution_number' => '1234',
            'resolution_date' => '2026-01-15',
        ]);
    }

    private function poliza(string $tipo, string $numero, string $estado, string $vence, string $creada, ?string $vehiculo = null): void
    {
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculo ?? $this->vehiculo,
            'document_type' => $tipo,
            'policy_number' => $numero,
            'issue_date' => '2025-01-01',
            'expiry_date' => $vence,
            'status' => $estado,
            'created_at' => $creada,
            'updated_at' => $creada,
        ]);
    }

    /** @return array<string, mixed> */
    private function datosFuec(?string $vehiculo = null): array
    {
        $vehiculo ??= $this->vehiculo;

        return [
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $vehiculo,
            'issue_date' => '2026-10-08',
            'effective_date' => '2026-10-08',
            'expiration_date' => '2026-10-09',
            'origin_route' => 'Origen',
            'destination_route' => 'Destino',
            'object_contract_uuid' => $this->objeto,
            'main_conductor_uuid' => $this->conductor,
            'contractor' => [
                'document_type_uuid' => $this->tipoDocumento,
                'document_number' => '123456789',
                'company_name' => 'Contratista Prueba',
                'address' => 'Calle 1',
                'telephone' => '3001234567',
                'contract_number' => 'C-001',
                'contracting_party_city' => 'Bogotá',
                'vehicle_uuid' => $vehiculo,
                'responsible_name' => 'Responsable',
                'responsible_document' => '987654321',
                'responsible_phone' => '3007654321',
                'responsible_address' => 'Calle 2',
            ],
        ];
    }

    public function test_crear_fuec_ignora_la_rcc_reemplazada_vencida(): void
    {
        $this->poliza('RCC', 'VIEJA-001', 'NO VIGENTE', '2026-01-01', '2026-02-01 10:00:00');
        $this->poliza('RCC', 'NUEVA-001', 'VIGENTE', '2027-12-31', '2026-09-01 10:00:00');
        $this->poliza('RCE', 'RCE-001', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00');
        $this->poliza('SOAT', 'SOAT-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');
        $this->poliza('RTM', 'RTM-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');

        $fuec = app(FuecService::class)->createFuec($this->datosFuec());

        $this->assertNotEmpty($fuec->uuid, 'El FUEC no se creó aunque la RCC vigente está al día');
    }

    public function test_crear_fuec_falla_si_la_rcc_viva_esta_vencida(): void
    {
        $this->poliza('RCC', 'VIEJA-001', 'NO VIGENTE', '2026-01-01', '2026-02-01 10:00:00');
        $this->poliza('RCC', 'NUEVA-001', 'VIGENTE', '2026-05-01', '2026-09-01 10:00:00');
        $this->poliza('RCE', 'RCE-001', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00');
        $this->poliza('SOAT', 'SOAT-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');
        $this->poliza('RTM', 'RTM-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');

        try {
            app(FuecService::class)->createFuec($this->datosFuec());
            $this->fail('El FUEC se creó con la RCC viva vencida');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('RCC', $e->getMessage());
            $this->assertStringContainsString('vencida', $e->getMessage());
            $this->assertStringContainsString('01/05/2026', $e->getMessage());
        }
    }

    public function test_crear_fuec_falla_si_el_soat_vivo_esta_vencido(): void
    {
        $this->poliza('RCC', 'NUEVA-001', 'VIGENTE', '2027-12-31', '2026-09-01 10:00:00');
        $this->poliza('RCE', 'RCE-001', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00');
        $this->poliza('SOAT', 'SOAT-VIEJO', 'NO VIGENTE', '2026-01-15', '2026-02-01 10:00:00');
        $this->poliza('SOAT', 'SOAT-001', 'VIGENTE', '2026-09-01', '2026-09-01 10:00:00');
        $this->poliza('RTM', 'RTM-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');

        try {
            app(FuecService::class)->createFuec($this->datosFuec());
            $this->fail('El FUEC se creó con el SOAT vivo vencido');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('SOAT', $e->getMessage());
            $this->assertStringContainsString('vencido', $e->getMessage());
            $this->assertStringContainsString('01/09/2026', $e->getMessage());
        }
    }

    public function test_crear_fuec_falla_sin_soat_registrado(): void
    {
        $this->poliza('RCC', 'NUEVA-001', 'VIGENTE', '2027-12-31', '2026-09-01 10:00:00');
        $this->poliza('RCE', 'RCE-001', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00');
        $this->poliza('RTM', 'RTM-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');

        try {
            app(FuecService::class)->createFuec($this->datosFuec());
            $this->fail('El FUEC se creó sin SOAT registrado');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('SOAT', $e->getMessage());
        }
    }

    public function test_crear_fuec_falla_si_la_rtm_viva_esta_vencida(): void
    {
        $this->poliza('RCC', 'NUEVA-001', 'VIGENTE', '2027-12-31', '2026-09-01 10:00:00');
        $this->poliza('RCE', 'RCE-001', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00');
        $this->poliza('SOAT', 'SOAT-001', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00');
        $this->poliza('RTM', 'RTM-001', 'VIGENTE', '2026-04-30', '2026-09-01 10:00:00');

        try {
            app(FuecService::class)->createFuec($this->datosFuec());
            $this->fail('El FUEC se creó con la RTM viva vencida');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('RTM', $e->getMessage());
            $this->assertStringContainsString('vencida', $e->getMessage());
            $this->assertStringContainsString('30/04/2026', $e->getMessage());
        }
    }

    public function test_crear_fuec_permite_vehiculo_nuevo_sin_rtm_por_gracia(): void
    {
        $nuevo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'RCC002',
            'registration_date' => '2026-06-01',
        ]);
        $this->poliza('RCC', 'NUEVA-002', 'VIGENTE', '2027-12-31', '2026-09-01 10:00:00', $nuevo);
        $this->poliza('RCE', 'RCE-002', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00', $nuevo);
        $this->poliza('SOAT', 'SOAT-002', 'VIGENTE', '2027-03-31', '2026-09-01 10:00:00', $nuevo);

        $fuec = app(FuecService::class)->createFuec($this->datosFuec($nuevo));

        $this->assertNotEmpty($fuec->uuid, 'El FUEC no se creó aunque la RTM está en periodo de gracia');
    }

    public function test_listado_muestra_la_poliza_viva_no_la_minima(): void
    {
        $this->poliza('RCC', 'VIEJA-001', 'NO VIGENTE', '2026-01-01', '2026-02-01 10:00:00');
        $this->poliza('RCC', 'NUEVA-001', 'VIGENTE', '2027-12-31', '2026-09-01 10:00:00');
        $this->poliza('RCE', 'RCE-001', 'VIGENTE', '2027-06-30', '2026-09-01 10:00:00');

        $pagina = app(VehicleDocumentService::class)->getAllVehicleDocumentsWithPagination(
            15, 1, '', $this->empresa, 'RCE,RCC', null, $this->vehiculo
        );

        $filas = $pagina->items();
        $this->assertNotEmpty($filas, 'El listado no devolvió el vehículo');
        $this->assertSame('2027-12-31', $filas[0]['expiry_date']);
        $this->assertSame('VIGENTE', $filas[0]['status']);
    }
}
