<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\GeneralException;
use App\Models\CapacityMovement;
use App\Models\User;
use App\Services\Fleet\CapacityMovementService;
use App\Services\Fleet\OperationCardService;
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
 * Regla del Ministerio de Transporte sobre cupos de capacidad transportadora:
 * descuento al aprobar capacidad (vehículo nuevo / ingreso por cambio de empresa),
 * liberación + cancelación de tarjeta al desvincular, y renovación que no mueve el
 * contador. Cubre el motor (CapacityMovementService), el guardia del CRUD de
 * tarjetas (OperationCardService) y el flujo de radicación (RadicacionService).
 */
class CuposCapacidadTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    private string $vehiculo;

    private string $capacidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Cupos SAS', 'is_active' => true]);
        $resolucion = $this->insertar('enabling_resolutions', ['company_uuid' => $this->empresa]);
        $this->capacidad = $this->insertar('conveyor_capacity', [
            'enabling_resolution_uuid' => $resolucion,
            'vehicle_type' => 'CAMION',
            'authorized_capacity' => 5,
            'current_capacity' => 5,
            'minimum_own_capacity' => 1,
            'status' => 1,
        ]);
        $this->vehiculo = $this->insertar('vehicles', ['company_uuid' => $this->empresa]);
    }

    private function cupoActual(): int
    {
        return (int) DB::table('conveyor_capacity')->where('uuid', $this->capacidad)->value('current_capacity');
    }

    private function registrar(string $tipo, ?string $procedure = null): CapacityMovement
    {
        return app(CapacityMovementService::class)->registrar([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'vehicle_type' => 'CAMION',
            'procedure_uuid' => $procedure ?? (string) Str::uuid(),
            'movement_type' => $tipo,
        ]);
    }

    private function cardNueva(array $extra = []): \App\Models\OperationCard
    {
        return app(OperationCardService::class)->createOperationCard(array_merge([
            'company_uuid' => $this->empresa,
            'vehicle_uuid' => $this->vehiculo,
            'affiliated_company' => 'Cupos SAS',
            'service_type' => 'PUBLICO',
            'transport_mode' => 'TERRESTRE',
            'issue_date' => '2026-10-01',
            'expiration_date' => '2027-10-01',
            'operating_card_number' => 'TO-'.Str::random(6),
            'operation_card_type' => 'NUEVA',
        ], $extra));
    }

    // ─── Motor de cupos ───

    public function test_la_capacidad_de_un_vehiculo_nuevo_descuenta_un_cupo(): void
    {
        $mov = $this->registrar(CapacityMovement::DESCUENTO_NUEVO);

        $this->assertSame(4, $this->cupoActual());
        $this->assertTrue($mov->applied);
        $this->assertSame(5, $mov->capacity_before);
        $this->assertSame(4, $mov->capacity_after);
    }

    public function test_el_mismo_paso_no_descuenta_dos_veces(): void
    {
        $proceso = (string) Str::uuid();
        $this->registrar(CapacityMovement::DESCUENTO_INGRESO, $proceso);
        $this->registrar(CapacityMovement::DESCUENTO_INGRESO, $proceso);

        $this->assertSame(4, $this->cupoActual());
        $this->assertSame(1, DB::table('capacity_movements')->where('procedure_uuid', $proceso)->count());
    }

    public function test_la_desvinculacion_libera_un_cupo(): void
    {
        DB::table('conveyor_capacity')->where('uuid', $this->capacidad)->update(['current_capacity' => 3]);

        $mov = $this->registrar(CapacityMovement::LIBERACION_DESVINCULACION);

        $this->assertSame(4, $this->cupoActual());
        $this->assertSame(4, $mov->capacity_after);
    }

    public function test_sin_cupos_disponibles_el_motor_rechaza_el_descuento(): void
    {
        DB::table('conveyor_capacity')->where('uuid', $this->capacidad)->update(['current_capacity' => 0]);

        $this->expectException(GeneralException::class);
        $this->registrar(CapacityMovement::DESCUENTO_NUEVO);
    }

    public function test_en_modo_auditoria_no_muta_el_cupo_pero_registra_el_movimiento(): void
    {
        config(['capacity.enforce' => false]);

        $mov = $this->registrar(CapacityMovement::DESCUENTO_NUEVO);

        $this->assertSame(5, $this->cupoActual());
        $this->assertFalse($mov->applied);
        $this->assertSame(4, $mov->capacity_after);
    }

    // ─── Guardia del CRUD de tarjetas ───

    public function test_la_renovacion_no_exige_ni_mueve_cupo(): void
    {
        $card = $this->cardNueva(['operation_card_type' => 'RENOVACION']);

        $this->assertSame('RENOVACION', $card->operation_card_type);
        $this->assertSame(5, $this->cupoActual());
        $this->assertSame(0, DB::table('capacity_movements')->count());
    }

    public function test_en_auditoria_la_tarjeta_nueva_sin_descuento_se_permite(): void
    {
        config(['capacity.enforce_on_new_operation_card' => false]);

        $card = $this->cardNueva();

        $this->assertSame('NUEVA', $card->operation_card_type);
        $this->assertSame('ACTIVA', $card->runt_status);
    }

    public function test_en_modo_estricto_la_tarjeta_nueva_sin_descuento_se_rechaza(): void
    {
        config(['capacity.enforce_on_new_operation_card' => true]);

        $this->expectException(GeneralException::class);
        $this->cardNueva();
    }

    public function test_la_tarjeta_nueva_vincula_el_descuento_pendiente(): void
    {
        $mov = $this->registrar(CapacityMovement::DESCUENTO_NUEVO);

        $card = $this->cardNueva();

        $this->assertSame($card->uuid, $mov->fresh()->operation_card_uuid);
        $this->assertNull(app(CapacityMovementService::class)->descuentoPendienteDeVehiculo($this->vehiculo, $this->empresa));
    }

    public function test_la_conciliacion_ajusta_el_cupo_al_libro_mayor(): void
    {
        $this->registrar(CapacityMovement::DESCUENTO_NUEVO); // 5 -> 4
        DB::table('conveyor_capacity')->where('uuid', $this->capacidad)->update(['current_capacity' => 5]);

        // Dry-run: informa pero no toca el cupo.
        $this->artisan('capacity:reconciliar')->assertSuccessful();
        $this->assertSame(5, $this->cupoActual());

        $this->artisan('capacity:reconciliar', ['--apply' => true])->assertSuccessful();
        $this->assertSame(4, $this->cupoActual());
    }

    // ─── Flujo de radicación ───
    private function admin(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Cupos', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $rol = Role::findOrCreate('ADMIN_EMPRESA_CUPOS', 'api');
        foreach (['radicacion_to.index', 'radicacion_to.create', 'radicacion_to.update'] as $p) {
            $rol->givePermissionTo(Permission::findOrCreate($p, 'api'));
        }
        $user->assignRole($rol);
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $this->empresa, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * @param  array<int, string>  $ruta
     * @return array{0: string, 1: array<string, string>}
     */
    private function expediente(string $linkType, array $ruta): array
    {
        $padre = $this->insertar('procedures', [
            'company_uuid' => $this->empresa, 'link_type' => $linkType, 'vehicle_uuid' => $this->vehiculo,
            'procedure_type' => $ruta[0], 'procedure_code' => 'EXP-'.Str::random(6), 'status' => 'EN_PROCESO',
        ]);
        $pasos = [];
        foreach ($ruta as $i => $tipo) {
            $pasos[$tipo] = $this->insertar('procedures', [
                'company_uuid' => $this->empresa, 'parent_procedure_uuid' => $padre, 'link_type' => $linkType,
                'vehicle_uuid' => $this->vehiculo, 'procedure_type' => $tipo,
                'procedure_code' => 'EXP-'.Str::random(6), 'status' => $i === 0 ? 'EN_PROCESO' : 'RECIBIDO',
            ]);
        }

        return [$padre, $pasos];
    }

    public function test_avanzar_la_capacidad_de_un_vehiculo_nuevo_descuenta_el_cupo(): void
    {
        [, $pasos] = $this->expediente('NUEVO_VEHICULO', ['CAPACIDAD_TRANSPORTADORA', 'TARJETA_DE_OPERACION']);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$pasos['CAPACIDAD_TRANSPORTADORA']}/avanzar")
            ->assertOk();

        $this->assertSame(4, $this->cupoActual());
        $this->assertSame(1, DB::table('capacity_movements')
            ->where('movement_type', CapacityMovement::DESCUENTO_NUEVO)
            ->where('vehicle_uuid', $this->vehiculo)
            ->count());
    }

    public function test_la_renovacion_no_mueve_el_cupo(): void
    {
        foreach (['SOAT', 'RTM'] as $tipo) {
            $this->insertar('vehicle_documents', [
                'company_uuid' => $this->empresa, 'vehicle_uuid' => $this->vehiculo,
                'document_type' => $tipo, 'expiry_date' => '2027-01-01',
            ]);
        }
        [, $pasos] = $this->expediente('RENOVACION', ['RENOVACION_TARJETA']);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$pasos['RENOVACION_TARJETA']}/avanzar")
            ->assertOk();

        $this->assertSame(5, $this->cupoActual());
        $this->assertSame(0, DB::table('capacity_movements')->count());
    }

    public function test_la_desvinculacion_libera_el_cupo_y_cancela_la_tarjeta(): void
    {
        $card = $this->insertar('operation_cards', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $this->vehiculo,
            'affiliated_company' => 'Cupos SAS', 'service_type' => 'PUBLICO', 'transport_mode' => 'TERRESTRE',
            'issue_date' => '2026-01-01', 'expiration_date' => '2027-01-01',
            'operating_card_number' => 'TO-VIEJA', 'status' => true,
        ]);
        DB::table('conveyor_capacity')->where('uuid', $this->capacidad)->update(['current_capacity' => 3]);
        [, $pasos] = $this->expediente('DESVINCULACION_MUTUO', ['DESVINCULACION_MUTUO']);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$pasos['DESVINCULACION_MUTUO']}/avanzar")
            ->assertOk();

        $this->assertSame(4, $this->cupoActual());
        $tarjeta = DB::table('operation_cards')->where('uuid', $card)->first();
        $this->assertSame('CANCELADA', $tarjeta->runt_status);
        $this->assertSame(0, (int) $tarjeta->status);
    }
}
