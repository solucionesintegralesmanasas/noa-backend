<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BillingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Alta y edición de proveedores tecnológicos por HTTP.
 *
 * Regresión del 2026-10-20: `driver` se validaba con un implode mal armado
 * (string en vez de array) y el POST respondía 500; el flujo solo estaba
 * cubierto insertando filas directamente.
 */
class ProveedoresBillingHttpTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Proveedores ApiDian Test']);
    }

    private function superadmin(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('SUPERADMIN', 'api'));

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function datosProveedor(array $extra = []): array
    {
        return $extra + [
            'company_uuid' => $this->empresa,
            'name' => 'Conexión ApiDian',
            'driver' => 'apidian',
            'server_url' => 'https://apidian-sandbox.test',
            'test_set_id' => null,
            'send_to_dian' => false,
            'settings' => null,
            'client_id' => 'sandbox@pruebas.test',
            'client_password' => 'clave_portal',
            'client_secret' => null,
            'email' => null,
            'start_date' => null,
            'end_date' => null,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function test_registra_proveedor_apidian_por_http_sin_devolver_secretos(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        Http::fake([
            'https://apidian-sandbox.test/api/auth/login' => Http::response([
                'success' => true,
                'access_token' => 'jwt_creado',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $respuesta = $this->postJson('/api/v1/billing/billing-providers', $this->datosProveedor(), [
            'X-Company-UUID' => $this->empresa,
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.driver', 'apidian')
            ->assertJsonPath('data.client_id', 'sandbox@pruebas.test')
            ->assertJsonMissingPath('data.client_secret')
            ->assertJsonMissingPath('data.client_password');

        $guardado = BillingProvider::where('company_uuid', $this->empresa)->firstOrFail();
        $this->assertSame('clave_portal', $guardado->client_password);
        $this->assertSame('jwt_creado', $guardado->client_secret);

        // En reposo queda cifrada, no en texto plano.
        $crudo = (string) DB::table('billing_providers')->where('uuid', $guardado->uuid)->value('client_password');
        $this->assertNotSame('clave_portal', $crudo);
        $this->assertSame('clave_portal', Crypt::decryptString($crudo));

        Http::assertSent(fn ($peticion) => $peticion->url() === 'https://apidian-sandbox.test/api/auth/login'
            && $peticion['email'] === 'sandbox@pruebas.test'
            && $peticion['password'] === 'clave_portal');
    }

    public function test_login_fallido_no_guarda_el_proveedor(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        Http::fake([
            'https://apidian-sandbox.test/api/auth/login' => Http::response(['message' => 'Credenciales inválidas'], 401),
        ]);

        $respuesta = $this->postJson('/api/v1/billing/billing-providers', $this->datosProveedor(), [
            'X-Company-UUID' => $this->empresa,
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.0.field', 'client_password');

        $this->assertSame(0, BillingProvider::where('company_uuid', $this->empresa)->count());
    }

    public function test_actualizar_sin_password_conserva_la_guardada(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        Http::fake([
            'https://apidian-sandbox.test/api/auth/login' => Http::response(['access_token' => 'jwt_creado'], 200),
        ]);

        $creado = $this->postJson('/api/v1/billing/billing-providers', $this->datosProveedor(), [
            'X-Company-UUID' => $this->empresa,
        ])->assertCreated()->json('data.uuid');

        $datos = $this->datosProveedor(['name' => 'Conexión renombrada']);
        unset($datos['client_password']);

        $this->putJson("/api/v1/billing/billing-providers/{$creado}", $datos, [
            'X-Company-UUID' => $this->empresa,
        ])->assertOk()->assertJsonPath('data.name', 'Conexión renombrada');

        $this->assertSame('clave_portal', BillingProvider::where('uuid', $creado)->firstOrFail()->client_password);
    }
}
