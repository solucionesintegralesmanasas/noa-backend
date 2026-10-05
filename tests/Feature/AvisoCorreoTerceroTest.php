<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ThirdParty;
use App\Models\User;
use App\Services\ThirdParties\ThirdPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * El usuario es único por correo: guardar un tercero con el correo de otro re-enlazaba al usuario y le cambiaba el rol
 * (pasó con TURISVAL y su conductor). El formulario avisa antes (email-check) y el backend rechaza con 422 al guardar.
 */
class AvisoCorreoTerceroTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $turisval;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->crearEmpresa();
        $this->turisval = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa, 'company_name' => 'TURISVAL S.A.S', 'document_number' => '901301544',
            'is_affiliate' => 1, 'is_active' => 1,
        ]);
        $this->usuario('AFILIADO', $this->empresa, $this->turisval, 'turisvalsas@hotmail.com');
        $this->admin = $this->usuario('ADMIN_EMPRESA', $this->empresa, null, 'admin@test.local');
    }

    private function crearEmpresa(): string
    {
        $uuid = (string) Str::uuid();
        $relleno = (string) Str::uuid();
        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid, 'person_type' => 'PERSONA JURIDICA', 'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $relleno, 'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa '.Str::random(5), 'municipality_uuid' => $relleno, 'address' => 'Calle 1',
            'email' => Str::random(6).'@empresa.test', 'tax_regime_uuid' => $relleno, 'web_page' => 'https://e.test',
            'legal_representative_name' => 'Rep', 'legal_representative_last_name' => 'Legal',
            'legal_representative_document_type' => 'CC', 'legal_representative_document_number' => '1',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function usuario(string $rol, string $empresa, ?string $tercero, string $email): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Prueba '.$rol, 'email' => $email,
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate($rol, 'api'));
        DB::table('company_user')->insert([
            'user_id' => $user->id, 'company_uuid' => $empresa, 'third_party_uuid' => $tercero,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function consultar(string $email, ?string $excluir = null, ?string $empresa = null)
    {
        Sanctum::actingAs($this->admin, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $empresa ?? $this->empresa])
            ->getJson('/api/v1/third-parties/email-check?email='.urlencode($email).($excluir ? '&exclude_uuid='.$excluir : ''));
    }

    public function test_avisa_cuando_el_correo_ya_es_el_usuario_de_otro_tercero(): void
    {
        $r = $this->consultar('turisvalsas@hotmail.com');

        $r->assertOk()->assertJsonPath('data.en_uso', true)
            ->assertJsonPath('data.tercero.nombre', 'TURISVAL S.A.S');
        $this->assertContains('AFILIADO', $r->json('data.tercero.roles'));
    }

    public function test_no_avisa_al_editar_el_mismo_tercero_dueno_del_correo(): void
    {
        $this->consultar('turisvalsas@hotmail.com', $this->turisval)
            ->assertOk()->assertJsonPath('data.en_uso', false);
    }

    public function test_no_avisa_con_un_correo_libre_ni_con_uno_que_no_tiene_tercero(): void
    {
        $this->consultar('libre@ejemplo.com')->assertOk()->assertJsonPath('data.en_uso', false);
        // El admin es usuario de la empresa pero no está enlazado a ningún tercero: no hay a quién re-enlazar.
        $this->consultar('admin@test.local')->assertOk()->assertJsonPath('data.en_uso', false);
    }

    public function test_el_aviso_no_revela_terceros_de_otra_empresa(): void
    {
        $otra = $this->crearEmpresa();
        $this->usuario('ADMIN_EMPRESA', $otra, null, 'otro-admin@test.local');
        DB::table('company_user')->where('user_id', $this->admin->id)->insert([
            'user_id' => $this->admin->id, 'company_uuid' => $otra, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->consultar('turisvalsas@hotmail.com', null, $otra)->assertOk()->assertJsonPath('data.en_uso', false);
    }

    private function datosTercero(): array
    {
        return [
            'company_uuid' => $this->empresa, 'person_type' => 'NATURAL', 'document_type_uuid' => (string) Str::uuid(),
            'document_number' => '94483254', 'first_name' => 'ALIRIO', 'last_name' => 'MATEUS',
            'email' => 'turisvalsas@hotmail.com', 'municipality_uuid' => (string) Str::uuid(),
            'tax_regime' => 'x', 'tax_responsibility_uuid' => (string) Str::uuid(), 'is_driver' => true,
        ];
    }

    public function test_no_permite_crear_un_tercero_con_el_correo_de_otro_tercero(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            app(ThirdPartyService::class)->createThirdParty($this->datosTercero());
            $this->fail('Debió rechazar el correo ya usado por TURISVAL');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertStringContainsString('TURISVAL', $e->errors()['email'][0]);
        }

        $this->assertFalse(DB::table('third_parties')->where('document_number', '94483254')->exists(), 'No debe quedar el tercero creado');
        $this->assertSame(
            $this->turisval,
            DB::table('company_user')->join('users', 'users.id', '=', 'company_user.user_id')
                ->where('users.email', 'turisvalsas@hotmail.com')->value('company_user.third_party_uuid'),
            'El usuario de TURISVAL no debe re-enlazarse'
        );
    }

    public function test_no_permite_editar_un_tercero_con_el_correo_de_otro_tercero(): void
    {
        Schema::disableForeignKeyConstraints();
        $conductor = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa, 'first_name' => 'ALIRIO', 'last_name' => 'MATEUS', 'document_number' => '94483254',
            'email' => 'alirio@ejemplo.com', 'is_driver' => 1, 'is_active' => 1,
        ]);

        try {
            app(ThirdPartyService::class)->updateThirdParty($conductor, ['email' => 'turisvalsas@hotmail.com']);
            $this->fail('Debió rechazar el correo ya usado por TURISVAL');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        $this->assertSame('alirio@ejemplo.com', ThirdParty::withoutGlobalScopes()->where('uuid', $conductor)->value('email'));
    }

    public function test_el_tercero_dueno_del_usuario_si_puede_guardarse_con_su_correo(): void
    {
        Schema::disableForeignKeyConstraints();
        app(ThirdPartyService::class)->updateThirdParty($this->turisval, ['email' => 'turisvalsas@hotmail.com', 'trade_name' => 'TURISVAL']);

        $this->assertSame('TURISVAL', ThirdParty::withoutGlobalScopes()->where('uuid', $this->turisval)->value('trade_name'));
    }

    public function test_exige_un_correo_valido(): void
    {
        $this->consultar('no-es-correo')->assertStatus(422);
    }
}
