<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Red de seguridad del módulo de radicación: endpoints de validación,
 * firma digital por enlace y generación de enlace. Aquí no había ninguna
 * prueba; protege el cambio de contrato de error al usar FormRequests.
 */
class RadicacionFirmaTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $padre;

    private string $hijo;

    private string $contrato;

    private string $token = 'token-de-prueba-1234';

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = $this->insertar('companies', ['business_name' => 'Expedientes TSB', 'is_active' => true]);
        $this->padre = $this->insertar('procedures', ['company_uuid' => $this->empresa]);
        $this->hijo = $this->insertar('procedures', ['company_uuid' => $this->empresa, 'parent_procedure_uuid' => $this->padre]);
        $this->contrato = $this->insertar('service_provision_contracts', [
            'company_uuid' => $this->empresa,
            'procedure_uuid' => $this->hijo,
            'contract_number' => 'SPC-001',
            'issue_date' => '2026-01-01',
            'start_date' => '2026-02-01',
            'end_date' => '2026-12-31',
            'duration' => 300,
            'status' => 'PENDIENTE_FIRMA',
        ]);
    }

    private function usuario(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prueba Radicacion',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('ADMIN_EMPRESA', 'api'));

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function firmaPendiente(array $extra = []): string
    {
        $this->insertar('contract_signatures', array_merge([
            'company_uuid' => $this->empresa,
            'contract_origin' => 'PRESTACION',
            'contract_uuid' => $this->contrato,
            'signer_role' => 'REP_LEGAL',
            'signer_name' => 'Firmante Prueba',
            'signer_document' => '123456789',
            'signer_email' => 'firma@test.local',
            'token_hash' => hash('sha256', $this->token),
            'expires_at' => now()->addDays(3),
            'status' => 'PENDIENTE',
        ], $extra));

        return hash('sha256', $this->token);
    }

    // Las rutas públicas viven en el grupo `signed:relative`: hay que pedirlas
    // con la firma temporal que genera la propia API.
    private function urlPublica(string $ruta, string $token): string
    {
        return URL::temporarySignedRoute($ruta, now()->addMinutes(5), ['token' => $token], false);
    }

    // ------------------------------------------------------------------

    public function test_expediente_rechaza_datos_invalidos(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);

        $this->postJson('/api/v1/procedure/radicacion/expediente', [])->assertStatus(422);
    }

    public function test_enlace_de_firma_rechaza_datos_invalidos(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);

        $this->postJson('/api/v1/procedure/radicacion/enlace-firma', [])->assertStatus(422);
    }

    public function test_txt_requiere_origin(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);

        $this->postJson("/api/v1/procedure/radicacion/{$this->padre}/txt", [])->assertStatus(422);
    }

    public function test_show_public_devuelve_el_detalle_de_la_firma(): void
    {
        $this->firmaPendiente();

        $this->getJson($this->urlPublica('api.v1.public.contracts.sign-show', $this->token))
            ->assertOk()
            ->assertJsonStructure(['data' => ['firmante', 'documento', 'estado', 'expira_en', 'expirado']])
            ->assertJsonPath('data.firmante', 'Firmante Prueba')
            ->assertJsonPath('data.estado', 'PENDIENTE');
    }

    public function test_show_public_con_token_invalido_da_404(): void
    {
        $this->getJson($this->urlPublica('api.v1.public.contracts.sign-show', 'no-existe'))->assertNotFound();
    }

    public function test_firmar_por_token_marca_la_firma_y_no_permite_repetir(): void
    {
        $this->firmaPendiente();

        $this->postJson($this->urlPublica('api.v1.public.contracts.sign', $this->token), [
            'signature_data' => 'data:image/png;base64,abc',
            'document_hash' => 'hash-documento',
        ])->assertOk();

        $row = DB::table('contract_signatures')->where('token_hash', hash('sha256', $this->token))->first();
        $this->assertSame('FIRMADO', $row->status);
        $this->assertNotNull($row->signed_at);

        $this->postJson($this->urlPublica('api.v1.public.contracts.sign', $this->token), [
            'signature_data' => 'data:image/png;base64,abc',
            'document_hash' => 'hash-documento',
        ])->assertStatus(422);
    }

    public function test_firmar_con_enlace_expirado_da_422(): void
    {
        $this->firmaPendiente(['expires_at' => now()->subDay()]);

        $this->postJson($this->urlPublica('api.v1.public.contracts.sign', $this->token), [
            'signature_data' => 'data:image/png;base64,abc',
        ])->assertStatus(422);
    }
}
