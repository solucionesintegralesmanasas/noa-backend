<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Administrations\BankDetailService;
use App\Services\Administrations\BranchService;
use App\Services\Administrations\CompanyService;
use App\Services\Catalogs\BrandService;
use App\Services\Catalogs\VehicleClassService;
use App\Services\Catalogs\WithholdingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Pruebas de ordenamiento server-side para Catálogos y Administración.
 */
class OrdenamientoCatalogosAdminTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa Admin Test', 'is_active' => true]);
    }

    public function test_ordena_marcas_por_descripcion(): void
    {
        $this->insertar('brands', ['description' => 'Zeta Motors']);
        $this->insertar('brands', ['description' => 'Alpha Motors']);

        $servicio = app(BrandService::class);
        $resAsc = $servicio->getAllBrandsWithPagination(10, 1, '', null, 'description', 'asc');
        $this->assertSame('Alpha Motors', $resAsc->items()[0]->description);

        $resDesc = $servicio->getAllBrandsWithPagination(10, 1, '', null, 'description', 'desc');
        $this->assertSame('Zeta Motors', $resDesc->items()[0]->description);
    }

    public function test_ordena_clases_vehiculo_por_descripcion(): void
    {
        $this->insertar('vehicle_class', ['class_code_class' => '02', 'description' => 'Zeta Clase']);
        $this->insertar('vehicle_class', ['class_code_class' => '01', 'description' => 'Alpha Clase']);

        $servicio = app(VehicleClassService::class);
        $resAsc = $servicio->getAllVehicleClassesWithPagination(10, 1, '', null, 'description', 'asc');
        $this->assertSame('Alpha Clase', $resAsc->items()[0]->description);

        $resDesc = $servicio->getAllVehicleClassesWithPagination(10, 1, '', null, 'description', 'desc');
        $this->assertSame('Zeta Clase', $resDesc->items()[0]->description);
    }

    public function test_ordena_sucursales_por_nombre(): void
    {
        $this->insertar('branches', ['company_uuid' => $this->empresa, 'name' => 'Sede Zeta', 'status' => 1]);
        $this->insertar('branches', ['company_uuid' => $this->empresa, 'name' => 'Sede Alpha', 'status' => 1]);

        $servicio = app(BranchService::class);
        $resAsc = $servicio->getAllBranchesWithPagination(10, 1, '', $this->empresa, 'name', 'asc');
        $this->assertSame('Sede Alpha', $resAsc->items()[0]->name);

        $resDesc = $servicio->getAllBranchesWithPagination(10, 1, '', $this->empresa, 'name', 'desc');
        $this->assertSame('Sede Zeta', $resDesc->items()[0]->name);
    }

    public function test_ordena_empresas_por_razon_social(): void
    {
        $this->insertar('companies', ['business_name' => 'Zeta Corp', 'is_active' => true]);
        $this->insertar('companies', ['business_name' => 'Alpha Corp', 'is_active' => true]);

        $servicio = app(CompanyService::class);
        $resAsc = $servicio->getAllCompaniesWithPagination(10, 1, '', null, 'business_name', 'asc');
        $nombres = collect($resAsc->items())->pluck('business_name');
        $this->assertSame('Alpha Corp', $nombres->first());

        $resDesc = $servicio->getAllCompaniesWithPagination(10, 1, '', null, 'business_name', 'desc');
        $nombresDesc = collect($resDesc->items())->pluck('business_name');
        $this->assertSame('Zeta Corp', $nombresDesc->first());
    }

    public function test_ordena_cuentas_bancarias_por_banco(): void
    {
        $this->insertar('bank_details', ['company_uuid' => $this->empresa, 'bank_name' => 'Banco Zeta', 'account_number' => '123', 'is_active' => true]);
        $this->insertar('bank_details', ['company_uuid' => $this->empresa, 'bank_name' => 'Banco Alpha', 'account_number' => '456', 'is_active' => true]);

        $servicio = app(BankDetailService::class);
        $resAsc = $servicio->getAllBankDetailsWithPagination(10, 1, '', $this->empresa, 'bank_name', 'asc');
        $this->assertSame('Banco Alpha', $resAsc->items()[0]->bank_name);

        $resDesc = $servicio->getAllBankDetailsWithPagination(10, 1, '', $this->empresa, 'bank_name', 'desc');
        $this->assertSame('Banco Zeta', $resDesc->items()[0]->bank_name);
    }

    public function test_ordena_retenciones_por_codigo(): void
    {
        $this->insertar('withholdings', ['code' => 'RET-Z', 'name' => 'Ret Zeta', 'is_active' => true]);
        $this->insertar('withholdings', ['code' => 'RET-A', 'name' => 'Ret Alpha', 'is_active' => true]);

        $servicio = app(WithholdingService::class);
        $resAsc = $servicio->getAllWithholdingsWithPagination(10, 1, '', null, 'code', 'asc');
        $this->assertSame('RET-A', $resAsc->items()[0]->code);

        $resDesc = $servicio->getAllWithholdingsWithPagination(10, 1, '', null, 'code', 'desc');
        $this->assertSame('RET-Z', $resDesc->items()[0]->code);
    }
}
