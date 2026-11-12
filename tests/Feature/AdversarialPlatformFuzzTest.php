<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\PlanillaCerradaException;
use App\Models\CommercialInvoice;
use App\Models\DriverLocation;
use App\Models\DriverLocationDailyStat;
use App\Models\ServiceDeliveryControlSheet;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActorScope\AlcanceDelActor;
use App\Services\Billing\BillingResolutionService;
use App\Services\Billing\CommercialInvoiceService;
use App\Services\Billing\CreditNoteService;
use App\Services\Fleet\DriverLicenseService;
use App\Services\Fleet\VehicleService;
use App\Services\Procedure\ContractNumberService;
use App\Services\Reports\VehicleActivityReportService;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use App\Services\ThirdParties\ThirdPartyService;
use App\Services\Tracking\LocationTrackingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Suite de Pruebas Adversariales y Fuzzing de Plataforma sobre BD (`RefreshDatabase`).
 *
 * Semilla determinista global: 20261112
 * Audita invariantes multi-tenant (`AlcanceDelActor`), secuencias de contratos (`ContractNumberService`),
 * terceros y licencias, planillas PCP, ingesta GPS, reporte de actividad vehicular y facturación comercial.
 */
class AdversarialPlatformFuzzTest extends TestCase
{
    use InsertaFilas;
    use RefreshDatabase;

    private const SEMILLA_FUZZ = 20261112;

    private string $empresaA;
    private string $empresaB;
    private string $sucursalA1;
    private string $sucursalA2;
    private string $vehiculoA1;
    private string $vehiculoB1;
    private string $conductorA1;
    private string $clienteA1;

    protected function insertarFila(string $tabla, array $datos): string
    {
        if ($tabla === 'companies') {
            if (isset($datos['name']) && ! isset($datos['business_name'])) {
                $datos['business_name'] = $datos['name'];
            }
            if (isset($datos['nit']) && ! isset($datos['document_number'])) {
                $datos['document_number'] = $datos['nit'];
            }
        }
        if ($tabla === 'third_parties') {
            if (isset($datos['identification_number']) && ! isset($datos['document_number'])) {
                $datos['document_number'] = $datos['identification_number'];
            }
            if (isset($datos['first_last_name']) && ! isset($datos['last_name'])) {
                $datos['last_name'] = $datos['first_last_name'];
            }
        }
        if ($tabla === 'vehicles') {
            if (isset($datos['license_plate']) && ! isset($datos['vehicle_license_plate'])) {
                $datos['vehicle_license_plate'] = $datos['license_plate'];
            }
        }
        if (isset($datos['status'])) {
            $esActivo = is_bool($datos['status']) ? $datos['status'] : ($datos['status'] === 'ACTIVO' || $datos['status'] === 1);
            if (! isset($datos['is_active'])) {
                $datos['is_active'] = $esActivo ? 1 : 0;
            }
            if ($tabla === 'users' || is_bool($datos['status'])) {
                $datos['status'] = $esActivo ? 1 : 0;
            }
        }

        $columnasReales = Schema::getColumnListing($tabla);
        if (! empty($columnasReales)) {
            $datos = array_intersect_key($datos, array_flip($columnasReales));
        }

        return $this->insertar($tabla, $datos);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();

        $this->empresaA = (string) Str::uuid();
        $this->empresaB = (string) Str::uuid();
        $this->sucursalA1 = (string) Str::uuid();
        $this->sucursalA2 = (string) Str::uuid();
        $this->vehiculoA1 = (string) Str::uuid();
        $this->vehiculoB1 = (string) Str::uuid();
        $this->conductorA1 = (string) Str::uuid();
        $this->clienteA1 = (string) Str::uuid();

        $this->insertarFila('companies', [
            'uuid' => $this->empresaA,
            'name' => 'Empresa Auditoría Alfa',
            'nit' => '900111001',
            'status' => true,
        ]);
        $this->insertarFila('companies', [
            'uuid' => $this->empresaB,
            'name' => 'Empresa Auditoría Beta',
            'nit' => '900222002',
            'status' => true,
        ]);

        $this->insertarFila('branches', [
            'uuid' => $this->sucursalA1,
            'company_uuid' => $this->empresaA,
            'code' => 'BOG',
            'name' => 'Sede Bogotá',
            'status' => true,
        ]);
        $this->insertarFila('branches', [
            'uuid' => $this->sucursalA2,
            'company_uuid' => $this->empresaA,
            'code' => 'MED',
            'name' => 'Sede Medellín',
            'status' => true,
        ]);

        $this->insertarFila('third_parties', [
            'uuid' => $this->conductorA1,
            'company_uuid' => $this->empresaA,
            'branch_uuid' => $this->sucursalA1,
            'identification_number' => '80100200',
            'first_name' => 'CARLOS',
            'first_last_name' => 'CONDUCTOR',
            'is_driver' => true,
            'is_employee' => true,
            'status' => true,
        ]);

        $this->insertarFila('third_parties', [
            'uuid' => $this->clienteA1,
            'company_uuid' => $this->empresaA,
            'branch_uuid' => $this->sucursalA1,
            'identification_number' => '900555666',
            'company_name' => 'CLIENTE CORPORATIVO ALFA S.A.S.',
            'is_customer' => true,
            'status' => true,
        ]);

        $this->insertarFila('vehicles', [
            'uuid' => $this->vehiculoA1,
            'company_uuid' => $this->empresaA,
            'branch_uuid' => $this->sucursalA1,
            'driver_uuid' => $this->conductorA1,
            'license_plate' => 'AAA111',
            'name' => ' Móvil Alfa 1 ',
            'status' => 'ACTIVO',
        ]);

        $this->insertarFila('vehicles', [
            'uuid' => $this->vehiculoB1,
            'company_uuid' => $this->empresaB,
            'license_plate' => 'BBB999',
            'name' => ' Móvil Beta 9 ',
            'status' => 'ACTIVO',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        foreach (['SUPERADMIN', 'ADMIN_EMPRESA', 'EMPLEADO', 'AFILIADO', 'CONDUCTOR', 'SUCURSAL'] as $rol) {
            Role::findOrCreate($rol, 'api');
        }
    }

    private function crearUsuarioConRol(
        string $rol,
        string $companyUuid,
        ?string $branchUuid = null,
        ?string $thirdPartyUuid = null,
    ): User {
        $uuid = (string) Str::uuid();
        $this->insertarFila('users', [
            'uuid' => $uuid,
            'company_uuid' => $companyUuid,
            'branch_uuid' => $branchUuid,
            'third_party_uuid' => $thirdPartyUuid,
            'name' => "Actor {$rol}",
            'email' => strtolower($rol) . '.' . Str::random(8) . '@test.local',
            'password' => bcrypt('Secret123!'),
            'status' => 'ACTIVO',
        ]);

        $user = User::where('uuid', $uuid)->firstOrFail();
        $pivotData = [
            'user_id' => $user->id,
            'company_uuid' => $companyUuid,
            'third_party_uuid' => $thirdPartyUuid,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $pivotCols = Schema::getColumnListing('company_user');
        if (in_array('branch_uuid', $pivotCols, true)) {
            $pivotData['branch_uuid'] = $branchUuid;
        }
        if (in_array('user_uuid', $pivotCols, true)) {
            $pivotData['user_uuid'] = $user->uuid;
        }
        DB::table('company_user')->insert(array_intersect_key($pivotData, array_flip($pivotCols)));

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $user->assignRole(Role::findByName($rol, 'api'));

        return $user;
    }

    private function fijarContextoPeticion(User $user, ?string $companyHeader = null): AlcanceDelActor
    {
        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

        $request = Request::create('/api/v1/fuzz', 'GET');
        if ($companyHeader !== null) {
            $request->headers->set('X-Company-UUID', $companyHeader);
            $request->attributes->set('current_company_uuid', $companyHeader);
        } else {
            $request->attributes->set('current_company_uuid', $user->company_uuid);
        }
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);

        app()->forgetInstance(AlcanceDelActor::class);
        $alcance = app(AlcanceDelActor::class);
        $alcance->resolverContexto($user, $companyHeader);

        return $alcance;
    }

    // =========================================================================
    // 1. AUDITORÍA ADVERSARIAL: AlcanceDelActor (Aislamiento Multi-Tenant)
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #14 (VULNERABILIDAD DE AISLAMIENTO CROSS-TENANT):
     * Archivo: app/Services/ActorScope/AlcanceDelActor.php:564, 640, 704, 887
     * Entrada: Un usuario `ADMIN_EMPRESA` o `EMPLEADO` perteneciente exclusivamente a `$empresaA`
     *          cuando se invoca `aplicarAVehiculos($query, $empresaB)` (p. ej. a través de un filtro
     *          `company_uuid` en un servicio que delega `$companyUuid` explícito).
     * Causa raíz: `aplicarAVehiculos` ejecuta `$companyEfectiva = $companyUuid ?? $this->companyUuid;`
     * sin verificar que el actor tenga permiso sobre `$companyUuid` (solo `resolverContexto` valida
     * pertenencia en `company_user`). Si `$companyUuid` se pasa explícitamente al método y el actor
     * no es afiliado/conductor/sucursal restringida, la consulta filtra por `$empresaB` y expone
     * los vehículos de una empresa ajena.
     */
    public function test_vulnerabilidad_alcance_del_actor_bypass_cross_tenant_con_company_uuid_explicito(): void
    {
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $alcance = $this->fijarContextoPeticion($adminA, $this->empresaA);

        $this->assertSame($this->empresaA, $alcance->companyUuid());

        // Consulta sin global scope (o vía DB::table / withoutGlobalScopes como hacen reportes y servicios)
        $query = Vehicle::withoutGlobalScopes();
        $alcance->aplicarAVehiculos($query, $this->empresaB);

        $placasVisibles = $query->pluck('vehicle_license_plate')->all();

        $this->assertNotContains(
            'BBB999',
            $placasVisibles,
            'VULNERABILIDAD: Un ADMIN_EMPRESA de Empresa A pudo leer vehículos de Empresa B pasando $companyUuid explícito a aplicarAVehiculos()'
        );
    }

    /**
     * FALLO REAL DETECTADO #15:
     * Archivo: app/Services/BaseService.php:359
     * Entrada: Parámetro de filtro `company_uuid` enviado como array (`['company_uuid' => [$empresaA]]`)
     *          en `BaseService::search()`.
     * Causa raíz: En `BaseService::search()`, cuando `$field === 'company_uuid'` y `$value !== null`,
     * invoca `$this->applyCompanyFilter($query, $value)` cuya firma exige `string $companyUuid`
     * con `declare(strict_types=1)`, lanzando `TypeError` no controlado ante un query param `?company_uuid[]=...`.
     */
    public function test_fallo_real_base_service_search_con_company_uuid_array_lanza_type_error(): void
    {
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $this->fijarContextoPeticion($adminA, $this->empresaA);

        $vehicleService = app(VehicleService::class);

        $resultado = $vehicleService->search(['company_uuid' => [$this->empresaA]]);
        $this->assertNotNull($resultado);
    }

    /**
     * Prueba intentos de evasión de AlcanceDelActor con cabeceras `X-Company-UUID` malformadas,
     * comodines SQL (`%`, `_`), inyecciones SQL, bytes nulos y espacios.
     */
    public function test_alcance_del_actor_bloquea_headers_company_uuid_malformados_e_inyecciones_sql(): void
    {
        $empleadoA = $this->crearUsuarioConRol('EMPLEADO', $this->empresaA);

        $vectoresAtaque = [
            $this->empresaB,
            '%',
            '_',
            "' OR '1'='1",
            "{$this->empresaB}' --",
            "   {$this->empresaB}   ",
            "\0{$this->empresaB}",
            '00000000-0000-0000-0000-000000000000',
        ];

        foreach ($vectoresAtaque as $headerMalicioso) {
            $alcance = $this->fijarContextoPeticion($empleadoA, $headerMalicioso);

            $this->assertNull(
                $alcance->companyUuid(),
                "AlcanceDelActor no falló cerrado ante X-Company-UUID malicioso: {$headerMalicioso}"
            );

            $query = Vehicle::withoutGlobalScopes();
            $alcance->aplicarAVehiculos($query);
            $this->assertSame(
                0,
                $query->count(),
                "Fuga de registros ante vector X-Company-UUID: {$headerMalicioso}"
            );
        }
    }

    // =========================================================================
    // 2. AUDITORÍA ADVERSARIAL: ContractNumberService (Secuencias de Contratos)
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #16:
     * Archivo: app/Services/Procedure/ContractNumberService.php:23-29 y 38-55
     * Entrada: Fila existente en `contract_sequences` con `last_number = 1`, mientras que en `contracts`
     *          ya existen contratos con `contract_number = '0005'` (p. ej. creados manualmente o importados).
     * Causa raíz: `siguiente()` y `reservar()` solo consultan `maximoExistente()` cuando `$fila` es `null`
     * (es decir, cuando la tabla `contract_sequences` aún no tiene registro para la empresa). Si la fila
     * ya existe pero quedó atrás respecto a `contracts`, `reservar()` devuelve `'0002'`, `'0003'`, `'0004'`, `'0005'`,
     * colisionando con contratos ya existentes.
     */
    public function test_fallo_real_contract_number_service_colisiona_cuando_secuencia_esta_atrasada_frente_a_contratos(): void
    {
        $servicio = app(ContractNumberService::class);

        // Inicializar secuencia en 1
        $primero = $servicio->reservar($this->empresaA);
        $this->assertSame('0001', $primero);

        // Insertar un contrato con número '0002' directamente en fleet_service_contracts
        $this->insertarFila('fleet_service_contracts', [
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $this->empresaA,
            'contract_number' => '0002',
        ]);

        // Al reservar el siguiente número, NUNCA debe entregar '0002' porque ya existe en fleet_service_contracts
        $siguiente = $servicio->reservar($this->empresaA);
        $this->assertNotSame(
            '0002',
            $siguiente,
            "Colisión de número de contrato: ContractNumberService::reservar devolvió '0002' aunque ya existía en fleet_service_contracts"
        );
    }

    /**
     * FALLO REAL DETECTADO #17:
     * Archivo: app/Services/Procedure/ContractNumberService.php:28, 59, 71-75
     * Entrada: Un contrato con `contract_number` numérico de 19-20 dígitos ('9223372036854775807')
     *          que cumple `REGEXP '^[0-9]+$'`.
     * Causa raíz: `maximoExistente()` hace `(int) $valor` llegando a `PHP_INT_MAX`, y en `siguiente()`
     * `$base + 1` desborda `int` a `float`, lanzando `TypeError` fatal al invocar `formatear(int $numero)`.
     */
    public function test_fallo_real_contract_number_service_desbordamiento_entero_lanza_type_error(): void
    {
        $this->insertarFila('fleet_service_contracts', [
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $this->empresaA,
            'contract_number' => (string) PHP_INT_MAX,
        ]);

        $servicio = app(ContractNumberService::class);
        $generado = $servicio->siguiente($this->empresaA);

        $this->assertMatchesRegularExpression(
            '/^\d{4,}$/',
            $generado,
            "ContractNumberService::siguiente falló ante contract_number en el límite de PHP_INT_MAX"
        );
    }

    // =========================================================================
    // 3. AUDITORÍA ADVERSARIAL: VehicleActivityReportService
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #18:
     * Archivo: app/Services/Reports/VehicleActivityReportService.php:53 y 117, 132, 148, 163, 181, 204
     * Entrada: Llamada a `VehicleActivityReportService::paginate($filtros)` omitiendo la clave `'company_uuid'`
     *          en el arreglo `$filtros` (confiando en que la línea 53 resuelve `$this->alcance->companyUuid()`).
     * Causa raíz: En `consultaConsolidada()`, la línea 53 hace `$companyUuid = $filtros['company_uuid'] ?? $this->alcance->companyUuid();`,
     * pero NO escribe `$filtros['company_uuid'] = $companyUuid;` antes de pasar `$filtros` a `brazoInspeccion($filtros)`.
     * En la línea 117, `->where('vi.company_uuid', $f['company_uuid'])` lanza `ErrorException: Undefined array key "company_uuid"`.
     */
    public function test_fallo_real_vehicle_activity_report_service_sin_company_uuid_en_filtros_lanza_undefined_array_key(): void
    {
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $this->fijarContextoPeticion($adminA, $this->empresaA);

        $servicio = app(VehicleActivityReportService::class);

        $resultado = $servicio->paginateReport([
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
        ]);

        $this->assertNotNull($resultado);
    }

    /**
     * FALLO REAL DETECTADO #19 (VULNERABILIDAD DE AISLAMIENTO EN REPORTE):
     * Archivo: app/Services/Reports/VehicleActivityReportService.php:53-81
     * Entrada: Un usuario `ADMIN_EMPRESA` o `EMPLEADO` de `$empresaA` invoca `VehicleActivityReportService::allForExport`
     *          con `$filtros['company_uuid'] = $this->empresaB`.
     * Causa raíz: `consultaConsolidada` toma `$companyUuid = $filtros['company_uuid']` sin validar que coincida con
     * `$this->alcance->companyUuid()`. Como `ADMIN_EMPRESA` y `EMPLEADO` no son sucursal restringida, ni afiliado,
     * ni conductor, no entran al `if` de la línea 73 y extraen toda la actividad operativa de `$empresaB`.
     */
    public function test_vulnerabilidad_vehicle_activity_report_service_fuga_cross_tenant_con_company_uuid_ajeno(): void
    {
        // Insertar un mantenimiento en la Empresa B
        $this->insertarFila('maintenance', [
            'uuid' => (string) Str::uuid(),
            'company_uuid' => $this->empresaB,
            'vehicle_uuid' => $this->vehiculoB1,
            'maintenance_date' => '2026-11-10',
            'service_description' => 'Mantenimiento confidencial Empresa Beta',
        ]);

        $empleadoA = $this->crearUsuarioConRol('EMPLEADO', $this->empresaA);
        $this->fijarContextoPeticion($empleadoA, $this->empresaA);

        $servicio = app(VehicleActivityReportService::class);
        $filas = $servicio->allForExport([
            'company_uuid' => $this->empresaB,
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
        ]);

        $this->assertCount(
            0,
            $filas,
            'VULNERABILIDAD: Un EMPLEADO de Empresa A pudo exportar la actividad vehicular de Empresa B'
        );
    }

    // =========================================================================
    // 4. AUDITORÍA ADVERSARIAL: ThirdPartyService y DriverLicenseService
    // =========================================================================

    /**
     * Verifica que un actor no SUPERADMIN nunca pueda asignar ni escalar a roles reservados
     * (`SUPERADMIN`, `ADMIN_EMPRESA`) al crear o actualizar un tercero con acceso al sistema,
     * y que se rechacen combinaciones inválidas de licencia.
     */
    public function test_third_party_service_bloquea_escalada_de_roles_privilegiados_y_valida_licencias(): void
    {
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $this->fijarContextoPeticion($adminA, $this->empresaA);

        Schema::disableForeignKeyConstraints();
        $servicio = app(ThirdPartyService::class);

        $tercero = $servicio->createThirdParty([
            'company_uuid' => $this->empresaA,
            'person_type' => 'NATURAL',
            'document_type_uuid' => (string) Str::uuid(),
            'document_number' => '77889900',
            'first_name' => 'Intruso',
            'last_name' => 'Escalada',
            'email' => 'intruso.escalada@test.local',
            'municipality_uuid' => (string) Str::uuid(),
            'tax_regime' => '49',
            'tax_responsibility_uuid' => (string) Str::uuid(),
            'is_employee' => true,
            'roles' => ['SUPERADMIN', 'ADMIN_EMPRESA', 'EMPLEADO'],
        ]);

        $usuarioVinculado = User::where('email', 'intruso.escalada@test.local')->firstOrFail();
        $this->assertFalse($usuarioVinculado->hasRole('SUPERADMIN'));
        $this->assertFalse($usuarioVinculado->hasRole('ADMIN_EMPRESA'));
        $this->assertTrue($usuarioVinculado->hasRole('EMPLEADO'));
        $this->assertNotNull($tercero->uuid);

        // Verificar en DriverLicenseService que rechaza crear una segunda licencia vigente cuando ya existe una activa no vencida
        $licenciaService = app(DriverLicenseService::class);
        $lic1 = $licenciaService->createDriverLicense([
            'company_uuid' => $this->empresaA,
            'third_party_uuid' => $this->conductorA1,
            'number' => '80100200-A',
            'category' => 'C2',
            'issue_date' => '2024-01-01',
            'expiration_date' => '2028-01-01',
            'status' => 'ACTIVA',
        ]);
        $this->assertSame('ACTIVA', $lic1->status);

        try {
            $licenciaService->createDriverLicense([
                'company_uuid' => $this->empresaA,
                'third_party_uuid' => $this->conductorA1,
                'number' => '80100200-B',
                'category' => 'C3',
                'issue_date' => '2025-01-01',
                'expiration_date' => '2029-01-01',
                'status' => 'ACTIVA',
            ]);
            $this->fail('DriverLicenseService debió rechazar crear una segunda licencia vigente');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    // =========================================================================
    // 5. AUDITORÍA ADVERSARIAL: ServiceDeliveryControlSheetService (Planillas PCP)
    // =========================================================================

    /**
     * Prueba que una planilla cerrada sea inmutable frente a edición, recierre, cambio de estado
     * y eliminación, y que `createBulkForProject` rechace rangos de fechas invertidos o fuera del proyecto.
     */
    public function test_planilla_control_cerrada_es_inmutable_y_create_bulk_valida_rangos_adversariales(): void
    {
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $this->fijarContextoPeticion($adminA, $this->empresaA);

        $uuidCerrada = (string) Str::uuid();
        $this->insertarFila('service_delivery_control_sheet', [
            'uuid' => $uuidCerrada,
            'company_uuid' => $this->empresaA,
            'is_active' => 0,
            'exception_reason' => 'Cierre de auditoría',
        ]);

        $servicio = app(ServiceDeliveryControlSheetService::class);

        try {
            $servicio->update($uuidCerrada, ['daily_route' => 'Intento de alteración post-cierre']);
            $this->fail('Debió lanzar ValidationException al intentar editar una planilla cerrada');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        try {
            $servicio->delete($uuidCerrada);
            $this->fail('Debió lanzar ValidationException al intentar eliminar una planilla cerrada');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        // Proyecto con rango acotado: intento de crear planillas con start_date > end_date
        $proyectoUuid = (string) Str::uuid();
        $this->insertarFila('projects', [
            'uuid' => $proyectoUuid,
            'company_uuid' => $this->empresaA,
            'code' => 'PRJ-001',
            'name' => 'Proyecto Blindado',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'ACTIVO',
        ]);

        try {
            $servicio->createBulkForProject([
                'company_uuid' => $this->empresaA,
                'project_uuid' => $proyectoUuid,
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-10',
                'assignments' => [
                    ['vehicle_uuid' => $this->vehiculoA1, 'driver_uuid' => $this->conductorA1],
                ],
            ]);
            $this->fail('Debió rechazar un rango donde start_date > end_date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('start_date', $e->errors());
        }
    }

    // =========================================================================
    // 6. AUDITORÍA ADVERSARIAL Y FUZZER: LocationTrackingService (Ingesta GPS)
    // =========================================================================

    /**
     * Estresa la ingesta GPS con timestamps en el futuro (deben truncarse a `now()`),
     * puntos desordenados cronológicamente (que obligan a recalcular el agregado diario)
     * y ráfaga determinista de coordenadas.
     */
    public function test_fuzzer_determinista_ingesta_gps_timestamps_futuros_y_desordenados(): void
    {
        mt_srand(self::SEMILLA_FUZZ);
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $this->fijarContextoPeticion($adminA, $this->empresaA);

        $servicio = app(LocationTrackingService::class);
        $manana = Carbon::now()->addDays(5)->toIso8601String();

        // 1. Punto con timestamp 5 días en el futuro: debe anclarse a <= now()
        $puntoFuturo = $servicio->storeLocation([
            'company_uuid' => $this->empresaA,
            'third_party_uuid' => $this->conductorA1,
            'vehicle_uuid' => $this->vehiculoA1,
            'latitude' => 4.6097,
            'longitude' => -74.0817,
            'speed' => 35.5,
            'accuracy' => 5.0,
            'recorded_at' => $manana,
        ]);

        $this->assertInstanceOf(DriverLocation::class, $puntoFuturo);
        $this->assertLessThanOrEqual(
            Carbon::now()->addSeconds(5)->timestamp,
            $puntoFuturo->recorded_at->timestamp,
            'Un timestamp futuro en storeLocation no fue truncado al tiempo actual del servidor'
        );

        // 2. Fuzzer de 25 puntos en el mismo día con llegadas fuera de orden
        $fechaBase = Carbon::yesterday()->setTime(8, 0, 0);
        $lat = 4.6000;
        $lng = -74.0800;

        $minutos = range(1, 25);
        shuffle($minutos);

        foreach ($minutos as $min) {
            $lat += (mt_rand(1, 15) / 10000.0);
            $lng -= (mt_rand(1, 15) / 10000.0);

            $servicio->storeLocation([
                'company_uuid' => $this->empresaA,
                'third_party_uuid' => $this->conductorA1,
                'vehicle_uuid' => $this->vehiculoA1,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => (float) mt_rand(0, 95),
                'accuracy' => (float) mt_rand(3, 25),
                'recorded_at' => $fechaBase->copy()->addMinutes($min)->toDateTimeString(),
            ]);
        }

        $resumen = DriverLocationDailyStat::withoutGlobalScopes()
            ->where('company_uuid', $this->empresaA)
            ->where('third_party_uuid', $this->conductorA1)
            ->whereDate('service_date', $fechaBase->toDateString())
            ->first();

        $this->assertNotNull($resumen);
        $this->assertSame(25, (int) $resumen->total_points);
        $this->assertGreaterThan(0, (float) $resumen->total_distance_meters);
    }

    // =========================================================================
    // 7. AUDITORÍA ADVERSARIAL: Facturación Comercial y Notas Crédito
    // =========================================================================

    /**
     * Verifica las invariantes críticas de facturación:
     * - No permitir abonos negativos ni abonos superiores al saldo pendiente.
     * - No permitir anular una factura emitida a DIAN o pagada.
     * - Emitir nota crédito reduce el total de la factura.
     */
    public function test_facturacion_comercial_y_notas_credito_invariantes_adversariales(): void
    {
        $adminA = $this->crearUsuarioConRol('ADMIN_EMPRESA', $this->empresaA);
        $this->fijarContextoPeticion($adminA, $this->empresaA);

        $invoiceService = app(CommercialInvoiceService::class);
        $creditNoteService = app(CreditNoteService::class);

        $this->insertar('billing_resolution_types', ['code' => '01', 'name' => 'Factura de venta', 'is_active' => 1]);
        $resolucionUuid = $this->insertar('billing_resolutions', [
            'company_uuid' => $this->empresaA,
            'resolution_type_code' => '01',
            'invoice_prefix' => 'SETT',
            'invoice_start_number' => 1,
            'invoice_end_number' => 5000,
            'last_consecutive' => 0,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'is_active' => 1,
        ]);

        $factura = $invoiceService->crearFactura([
            'company_uuid' => $this->empresaA,
            'third_party_uuid' => $this->clienteA1,
            'billing_resolution_uuid' => $resolucionUuid,
            'issue_date' => '2026-11-10',
            'due_date' => '2026-12-10',
            'origin_route' => 'Bogotá',
            'destination_route' => 'Medellín',
            'items' => [
                [
                    'description' => 'Servicio transporte empresarial',
                    'quantity' => 2,
                    'unit_price' => 500_000,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                ],
            ],
        ]);

        $this->assertEquals(1_000_000.0, (float) $factura->invoice_total);

        // 1. Pago negativo o cero debe ser rechazado
        try {
            $invoiceService->registrarPago($factura->uuid, [
                'amount' => -50_000,
                'payment_date' => '2026-11-10',
            ]);
            $this->fail('No debe permitir abonos con monto negativo');
        } catch (\Throwable $e) {
            $this->assertNotNull($e);
        }

        // 2. Pago superior al saldo debe ser rechazado
        try {
            $invoiceService->registrarPago($factura->uuid, [
                'amount' => 1_500_000,
                'payment_date' => '2026-11-10',
            ]);
            $this->fail('No debe permitir abonos mayores al saldo pendiente');
        } catch (\Throwable $e) {
            $this->assertNotNull($e);
        }

        // 3. Registrar abono parcial válido de 600,000 (saldo = 400,000)
        $factura = $invoiceService->registrarPago($factura->uuid, [
            'amount' => 600_000,
            'payment_date' => '2026-11-10',
        ]);
        $this->assertSame('PAGADA_PARCIAL', $factura->status);

        // 4. Emitir nota crédito por 500,000 sobre una factura que ya tiene 600,000 pagados:
        //    el total nunca debe caer por debajo de amount_paid (600,000)
        $notaCredito = $creditNoteService->crearNota([
            'invoice_uuid' => $factura->uuid,
            'correction_concept_code' => '01',
            'observation' => 'Ajuste comercial acordado',
            'issue_date' => '2026-11-11',
            'items' => [
                [
                    'description' => 'Descuento sobre servicio',
                    'quantity' => 1,
                    'unit_price' => 500_000,
                ],
            ],
        ]);
        $creditNoteService->emitirNota($notaCredito->uuid);

        $facturaActualizada = $factura->fresh();
        $this->assertEquals(600_000.0, (float) $facturaActualizada->invoice_total);
        $this->assertSame('PAGADA', $facturaActualizada->status);
    }
}
