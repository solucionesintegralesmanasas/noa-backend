<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Fleet\OperationCardService;
use App\Services\Fleet\VehicleDocumentService;
use App\Services\Fleet\VehicleService;
use App\Services\ThirdParties\ThirdPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004: los listados paginados de flota declaran su presupuesto.
 * Agregar una relación no debe multiplicar las consultas por fila.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class ListadosFlotaConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    private string $marca;

    private string $clase;

    private string $tercero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa listados', 'is_active' => true]);
        $this->marca = $this->insertar('brands', ['description' => 'Marca P']);
        $this->clase = $this->insertar('vehicle_class', ['description' => 'Clase P']);
        $this->tercero = $this->insertar('third_parties', ['company_uuid' => $this->empresa, 'first_name' => 'Tercero', 'last_name' => 'Fijo']);
    }

    private function vehiculo(string $placa): string
    {
        return $this->insertar('vehicles', [
            'company_uuid' => $this->empresa, 'vehicle_license_plate' => $placa, 'is_active' => true,
            'brand_uuid' => $this->marca, 'vehicle_class_uuid' => $this->clase, 'third_party_uuid' => $this->tercero,
        ]);
    }

    private function sembrar(int $n, string $prefijo, int $desde = 1): void
    {
        for ($i = $desde; $i < $desde + $n; $i++) {
            $placa = $prefijo.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $v = $this->vehiculo($placa);
            $this->insertar('vehicle_documents', [
                'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'document_type' => 'SOAT',
                'expiry_date' => '2026-12-31', 'status' => 'VIGENTE',
            ]);
            $this->insertar('operation_cards', [
                'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'operating_card_number' => 'TC-'.$placa,
                'expiration_date' => '2026-12-31', 'status' => true,
            ]);
            $this->insertar('third_parties', [
                'company_uuid' => $this->empresa, 'first_name' => 'Tercero', 'last_name' => $placa,
            ]);
        }
    }

    public function test_el_listado_de_vehiculos_no_crece_con_las_filas(): void
    {
        $this->sembrar(2, 'VEH');
        $medir = fn () => app(VehicleService::class)->getAllVehiclesWithPagination(15, 1, '', $this->empresa);
        $ampliar = fn () => $this->sembrar(13, 'VEH', 3);

        // Cordura: el listado devuelve filas (sin `company_uuid` daría un tope falso).
        $this->assertMedicionConDatos($medir()->total(), 'Listado de vehículos');
        $this->assertConteoConstante($medir, $ampliar, 'Listado de vehículos');
        $this->assertPresupuesto(6, $medir, 'Listado de vehículos'); // medido: 6 (conteo + página + 4 relaciones)
    }

    public function test_el_listado_de_documentos_no_crece_con_las_filas(): void
    {
        $this->sembrar(2, 'DOC');
        $medir = fn () => app(VehicleDocumentService::class)->getAllVehicleDocumentsWithPagination(15, 1, '', $this->empresa);
        $ampliar = fn () => $this->sembrar(13, 'DOC', 3);

        $this->assertMedicionConDatos($medir()->total(), 'Listado de documentos');
        $this->assertConteoConstante($medir, $ampliar, 'Listado de documentos');
        $this->assertPresupuesto(4, $medir, 'Listado de documentos'); // medido: 4 (conteo + página + vehículo + tercero)
    }

    public function test_el_listado_de_tarjetas_no_crece_con_las_filas(): void
    {
        $this->sembrar(2, 'TAR');
        $medir = fn () => app(OperationCardService::class)->getAllOperationCardsWithPagination(15, 1, '', $this->empresa);
        $ampliar = fn () => $this->sembrar(13, 'TAR', 3);

        $this->assertMedicionConDatos($medir()->total(), 'Listado de tarjetas');
        $this->assertConteoConstante($medir, $ampliar, 'Listado de tarjetas');
        $this->assertPresupuesto(3, $medir, 'Listado de tarjetas'); // medido: 3 (conteo + página + vehículo)
    }

    public function test_el_listado_de_terceros_no_crece_con_las_filas(): void
    {
        $this->sembrar(2, 'TER');
        $medir = fn () => app(ThirdPartyService::class)->getAllThirdPartiesWithPagination(15, 1, '', $this->empresa);
        $ampliar = fn () => $this->sembrar(13, 'TER', 3);

        $this->assertMedicionConDatos($medir()->total(), 'Listado de terceros');
        $this->assertConteoConstante($medir, $ampliar, 'Listado de terceros');
        $this->assertPresupuesto(4, $medir, 'Listado de terceros'); // medido: 4 (conteo + página + licencias)
    }
}
