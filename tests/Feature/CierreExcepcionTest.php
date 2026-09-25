<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceDeliveryControlSheet;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SPEC-002 §4 y §7.6 — Cierre con excepción sobre HTTP real.
 *
 * Comprueba la cadena completa: Sanctum + permiso Spatie + servicio + 422/403.
 */
class CierreExcepcionTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = '/api/v1/control-sheets/service-delivery-control-sheets';

    private function usuarioConPermiso(?string $permiso): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario Prueba',
            'email' => 'prueba'.Str::random(6).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        // Spatie exige el guard configurado del proyecto para estas rutas.
        $guard = 'api';
        setPermissionsTeamId(null);

        $role = Role::findOrCreate('ADMIN_EMPRESA', $guard);

        if ($permiso !== null) {
            $p = Permission::findOrCreate($permiso, $guard);
            $role->givePermissionTo($p);
        }

        $user->assignRole($role);

        return $user->fresh();
    }

    private function planilla(bool $activa): ServiceDeliveryControlSheet
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('service_delivery_control_sheet')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_uuid' => (string) Str::uuid(),
            'service_date' => '2026-09-20',
            'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
            'day_kind' => 'operacion',
            'is_active' => $activa,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return ServiceDeliveryControlSheet::query()->findOrFail($id);
    }

    public function test_quien_no_tiene_el_permiso_recibe_403(): void
    {
        $planilla = $this->planilla(true);
        $usuario = $this->usuarioConPermiso('service_delivery_control_sheets.index');

        $this->actingAs($usuario, 'sanctum')
            ->postJson(self::RUTA."/{$planilla->uuid}/close-exception", [
                'exception_reason' => 'Vía cerrada por derrumbe',
            ])
            ->assertForbidden();
    }

    public function test_quien_tiene_el_permiso_cierra_con_excepcion(): void
    {
        $planilla = $this->planilla(true);
        $usuario = $this->usuarioConPermiso('service_delivery_control_sheets.close_exception');

        $this->actingAs($usuario, 'sanctum')
            ->postJson(self::RUTA."/{$planilla->uuid}/close-exception", [
                'exception_reason' => 'Vehículo en mantenimiento correctivo',
            ])
            ->assertOk();

        $cerrada = $planilla->fresh();
        $this->assertFalse($cerrada->is_active);
        $this->assertSame('Vehículo en mantenimiento correctivo', $cerrada->motivoExcepcion());
        $this->assertNotNull($cerrada->exception_approved_at);
        $this->assertSame(
            ServiceDeliveryControlSheet::ESTADO_CERRADA_CON_EXCEPCION,
            $cerrada->estadoAdministrativo(false)
        );
    }

    public function test_sin_motivo_devuelve_422(): void
    {
        $planilla = $this->planilla(true);
        $usuario = $this->usuarioConPermiso('service_delivery_control_sheets.close_exception');

        // La API envuelve la validación en error.details (no en `errors`).
        $this->actingAs($usuario, 'sanctum')
            ->postJson(self::RUTA."/{$planilla->uuid}/close-exception", [
                'exception_reason' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.0.field', 'exception_reason');
    }

    public function test_no_se_puede_aprobar_excepcion_sobre_una_ya_cerrada(): void
    {
        $planilla = $this->planilla(false);
        $usuario = $this->usuarioConPermiso('service_delivery_control_sheets.close_exception');

        $this->actingAs($usuario, 'sanctum')
            ->postJson(self::RUTA."/{$planilla->uuid}/close-exception", [
                'exception_reason' => 'Intento tardío',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.0.field', 'close_exception');
    }

    public function test_sin_autenticacion_devuelve_401(): void
    {
        $planilla = $this->planilla(true);

        $this->postJson(self::RUTA."/{$planilla->uuid}/close-exception", [
            'exception_reason' => 'Sin sesión',
        ])->assertUnauthorized();
    }
}
