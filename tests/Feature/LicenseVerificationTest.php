<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

class LicenseVerificationTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private function crearLicencia(array $datos = []): License
    {
        $fila = $datos + [
            'company_uuid' => (string) Str::uuid(),
            'license_key' => 'LIC-'.strtoupper(Str::random(10)),
            'signature' => hash('sha256', Str::random(16)),
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'status' => 'active',
            'online_verification_enabled' => true,
            'offline_verifications' => 0,
            'online_verifications' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('licenses')->insert($fila);

        return License::where('license_key', $fila['license_key'])->firstOrFail();
    }

    private function crearUsuarioConRol(string $rol): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario '.$rol,
            'email' => Str::random(10).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate($rol, 'api'));

        return $user->fresh();
    }

    public function test_verificar_clave_valida_online(): void
    {
        $licencia = $this->crearLicencia();

        $respuesta = $this->getJson("/api/v1/license/verify/{$licencia->license_key}");

        $respuesta->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'valid',
                'data' => [
                    'status' => 'valid',
                    'company_uuid' => $licencia->company_uuid,
                    'mode' => 'online_verified',
                ],
                'message' => 'Licencia verificada exitosamente',
            ]);

        $this->assertSame(1, $licencia->fresh()->online_verifications);
    }

    public function test_verificar_clave_valida_fallback_offline(): void
    {
        // Simulamos fallback offline desactivando la verificación online en verifyKey
        // utilizando una clave en BD que se recupera vía verifyOffline
        $licencia = $this->crearLicencia([
            'online_verification_enabled' => false,
        ]);

        // Si verifyKey() por alguna razón no valida (ej. clave no HMAC o forzado a offline),
        // verifyOffline incrementa offline_verifications.
        // Forzamos el camino offline llamando directamente a verifyOffline a través de verify()
        $resultado = app(\App\Services\LicenseService::class)->verifyOffline($licencia->license_key);

        $this->assertNotNull($resultado);
        $this->assertSame('valid', $resultado['status']);
        $this->assertSame(1, $licencia->fresh()->offline_verifications);
    }

    public function test_verificar_clave_expirada(): void
    {
        $licencia = $this->crearLicencia([
            'expiry_date' => '2020-01-01',
        ]);

        $respuesta = $this->getJson("/api/v1/license/verify/{$licencia->license_key}");

        $respuesta->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'expired',
                'message' => 'Licencia vencida. Contacte a su administrador.',
            ]);

        $this->assertSame('expired', $licencia->fresh()->status);
    }

    public function test_verificar_clave_invalida_o_inexistente(): void
    {
        $respuesta = $this->getJson('/api/v1/license/verify/CODIGO-NO-EXISTENTE-999');

        $respuesta->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'invalid',
                'message' => 'Código de licencia inválido o no encontrado',
            ]);
    }

    public function test_verificar_clave_ausente_o_espacios(): void
    {
        $respuesta = $this->getJson('/api/v1/license/verify/%20%20');

        $respuesta->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'invalid',
            ]);

        $respuestaSinClave = $this->getJson('/api/v1/license/verify');
        $respuestaSinClave->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => 'invalid',
            ]);
    }

    public function test_revocar_licencia_requiere_superadmin(): void
    {
        $licencia = $this->crearLicencia();

        // 1. Visitante no autenticado
        $this->postJson('/api/v1/license/revoke', ['license_key' => $licencia->license_key])
            ->assertStatus(401);

        // 2. Usuario con rol EMPLEADO
        $empleado = $this->crearUsuarioConRol('EMPLEADO');
        Sanctum::actingAs($empleado, ['*']);

        $this->postJson('/api/v1/license/revoke', ['license_key' => $licencia->license_key])
            ->assertStatus(403);

        // 3. SUPERADMIN revoca exitosamente
        $superadmin = $this->crearUsuarioConRol('SUPERADMIN');
        Sanctum::actingAs($superadmin, ['*']);

        $this->postJson('/api/v1/license/revoke', ['license_key' => $licencia->license_key])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Licencia revocada',
            ]);

        $this->assertSame('revoked', $licencia->fresh()->status);
    }

    public function test_revocar_licencia_valida_campos_y_existencia(): void
    {
        $superadmin = $this->crearUsuarioConRol('SUPERADMIN');
        Sanctum::actingAs($superadmin, ['*']);

        // Sin clave
        $this->postJson('/api/v1/license/revoke', ['license_key' => ''])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        // Clave inexistente
        $this->postJson('/api/v1/license/revoke', ['license_key' => 'INEXISTENTE-123'])
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_renovar_licencia_requiere_superadmin_y_actualiza_fecha(): void
    {
        $licencia = $this->crearLicencia();
        $nuevaFecha = now()->addYear()->format('Y-m-d');

        // Empleado rechazado con 403
        $empleado = $this->crearUsuarioConRol('EMPLEADO');
        Sanctum::actingAs($empleado, ['*']);

        $this->postJson('/api/v1/license/renew', [
            'license_key' => $licencia->license_key,
            'new_expiry_date' => $nuevaFecha,
        ])->assertStatus(403);

        // SUPERADMIN exitoso
        $superadmin = $this->crearUsuarioConRol('SUPERADMIN');
        Sanctum::actingAs($superadmin, ['*']);

        $this->postJson('/api/v1/license/renew', [
            'license_key' => $licencia->license_key,
            'new_expiry_date' => $nuevaFecha,
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'new_expiry' => $nuevaFecha,
            ]);

        $this->assertSame($nuevaFecha, $licencia->fresh()->expiry_date->format('Y-m-d'));
    }

    public function test_detalle_licencia_accesible_para_superadmin_y_admin_empresa(): void
    {
        $licencia = $this->crearLicencia();

        // No autenticado -> 401
        $this->getJson("/api/v1/license/detail/{$licencia->license_key}")
            ->assertStatus(401);

        // ADMIN_EMPRESA -> 200
        $admin = $this->crearUsuarioConRol('ADMIN_EMPRESA');
        Sanctum::actingAs($admin, ['*']);

        $this->getJson("/api/v1/license/detail/{$licencia->license_key}")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'license_key' => $licencia->license_key,
                ],
            ]);

        // SUPERADMIN -> 200
        $superadmin = $this->crearUsuarioConRol('SUPERADMIN');
        Sanctum::actingAs($superadmin, ['*']);

        $this->getJson("/api/v1/license/detail/{$licencia->license_key}")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'license_key' => $licencia->license_key,
                ],
            ]);

        // Clave inexistente -> 404
        $this->getJson('/api/v1/license/detail/CLAVE-INEXISTENTE')
            ->assertStatus(404);
    }
}
