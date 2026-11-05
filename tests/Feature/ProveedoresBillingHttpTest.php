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
 * Alta y edición de proveedores tecnológicos (driver único: Factus) por HTTP.
 *
 * Al guardar con credenciales, NOA inicia sesión OAuth2 con Factus y guarda
 * access_token + refresh_token cifrados. El `driver` lo fija el backend a
 * `factus`; el cliente no lo elige.
 */
class ProveedoresBillingHttpTest extends TestCase
{
    use InsertaFilas, RefreshDatabase;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Proveedores Factus Test']);
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
            'name' => 'Conexión Factus',
            'server_url' => 'https://factus-sandbox.test',
            'send_to_dian' => false,
            'settings' => ['numbering_range_id' => 389],
            'client_id' => '9e1a-4f2c',
            'client_secret' => 'app_secret',
            'email' => 'usuario@factus.test',
            'client_password' => 'clave_factus',
            'is_active' => true,
        ];
    }

    public function test_registra_proveedor_factus_por_http_y_obtiene_tokens(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        Http::fake([
            'https://factus-sandbox.test/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'access_factus',
                'refresh_token' => 'refresh_factus',
            ], 200),
        ]);

        $respuesta = $this->postJson('/api/v1/billing/billing-providers', $this->datosProveedor(), [
            'X-Company-UUID' => $this->empresa,
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.driver', 'factus')
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.refresh_token')
            ->assertJsonMissingPath('data.client_secret')
            ->assertJsonMissingPath('data.client_password');

        $guardado = BillingProvider::where('company_uuid', $this->empresa)->firstOrFail();
        $this->assertSame('factus', $guardado->driver);
        $this->assertSame('access_factus', $guardado->access_token);
        $this->assertSame('refresh_factus', $guardado->refresh_token);
        $this->assertSame(389, $guardado->settings['numbering_range_id']);

        // En reposo los tokens y secretos quedan cifrados.
        $this->assertSame('access_factus', Crypt::decryptString((string) DB::table('billing_providers')->where('uuid', $guardado->uuid)->value('access_token')));
        $this->assertSame('app_secret', Crypt::decryptString((string) DB::table('billing_providers')->where('uuid', $guardado->uuid)->value('client_secret')));

        Http::assertSent(fn ($peticion) => $peticion->url() === 'https://factus-sandbox.test/oauth/token'
            && $peticion['grant_type'] === 'password'
            && $peticion['client_id'] === '9e1a-4f2c'
            && $peticion['client_secret'] === 'app_secret'
            && $peticion['username'] === 'usuario@factus.test'
            && $peticion['password'] === 'clave_factus');
    }

    public function test_login_factus_fallido_no_guarda_el_proveedor(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        Http::fake([
            'https://factus-sandbox.test/oauth/token' => Http::response(['message' => 'The user credentials were incorrect.'], 401),
        ]);

        $respuesta = $this->postJson('/api/v1/billing/billing-providers', $this->datosProveedor(), [
            'X-Company-UUID' => $this->empresa,
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'client_password');

        $this->assertSame(0, BillingProvider::where('company_uuid', $this->empresa)->count());
    }

    public function test_actualizar_sin_password_conserva_las_credenciales(): void
    {
        Sanctum::actingAs($this->superadmin(), ['*']);

        Http::fake([
            'https://factus-sandbox.test/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'access_factus',
                'refresh_token' => 'refresh_factus',
            ], 200),
        ]);

        $creado = $this->postJson('/api/v1/billing/billing-providers', $this->datosProveedor(), [
            'X-Company-UUID' => $this->empresa,
        ])->assertCreated()->json('data.uuid');

        $datos = $this->datosProveedor(['name' => 'Conexión renombrada']);
        unset($datos['client_password']);

        $this->putJson("/api/v1/billing/billing-providers/{$creado}", $datos, [
            'X-Company-UUID' => $this->empresa,
        ])->assertOk()->assertJsonPath('data.name', 'Conexión renombrada');

        $guardado = BillingProvider::where('uuid', $creado)->firstOrFail();
        $this->assertSame('clave_factus', $guardado->client_password);
        $this->assertSame('access_factus', $guardado->access_token);
    }
}
