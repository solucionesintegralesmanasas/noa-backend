<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ThirdParties\ThirdPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Desde el 2026-10-19 un tercero puede combinar tipos (p. ej. empleado y afiliado) y llevar
 * varios roles de acceso: un empleado de la empresa también puede ser el coordinador de una sede.
 * Antes la regla era "empleado = único tipo y único rol" (se retiró de los FormRequests y del
 * formulario). La sincronización sustituye solo los roles gestionados (EMPLEADO, AFILIADO,
 * CONDUCTOR) y preserva los demás (p. ej. COORDINADOR_SEDE). Los roles reservados
 * (SUPERADMIN, ADMIN_EMPRESA) no se pueden asignar por el flujo de terceros más que
 * a un SUPERADMIN (se retiran de la petición y se registra el aviso).
 */
class TerceroEmpleadoCombinadoTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);
        foreach (['EMPLEADO', 'AFILIADO'] as $rol) {
            Role::findOrCreate($rol, 'api');
        }
        Role::findOrCreate('COORDINADOR_SEDE', 'api');
        $this->empresa = $this->crearEmpresa();
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

    private function vinculadoDe(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_un_empleado_puede_ser_ademas_afiliado_y_coordinador(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $tercero = app(ThirdPartyService::class)->createThirdParty([
                'company_uuid' => $this->empresa, 'person_type' => 'NATURAL',
                'document_type_uuid' => (string) Str::uuid(), 'document_number' => '1090123456',
                'first_name' => 'MARTA', 'last_name' => 'GOMEZ', 'email' => 'marta.empleada@test.local',
                'municipality_uuid' => (string) Str::uuid(), 'tax_regime' => '48',
                'tax_responsibility_uuid' => (string) Str::uuid(),
                'is_employee' => true, 'is_affiliate' => true,
                'roles' => ['EMPLEADO', 'AFILIADO', 'COORDINADOR_SEDE'],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->assertTrue((bool) $tercero->is_employee);
        $this->assertTrue((bool) $tercero->is_affiliate);

        $usuario = $this->vinculadoDe('marta.empleada@test.local');
        $this->assertTrue($usuario->hasRole('EMPLEADO'));
        $this->assertTrue($usuario->hasRole('AFILIADO'));
        $this->assertTrue($usuario->hasRole('COORDINADOR_SEDE'));
    }

    public function test_al_editar_se_reemplazan_los_gestionados_y_se_preservan_los_demas(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $tercero = app(ThirdPartyService::class)->createThirdParty([
                'company_uuid' => $this->empresa, 'person_type' => 'NATURAL',
                'document_type_uuid' => (string) Str::uuid(), 'document_number' => '1090654321',
                'first_name' => 'PEDRO', 'last_name' => 'CID', 'email' => 'pedro.empleado@test.local',
                'municipality_uuid' => (string) Str::uuid(), 'tax_regime' => '48',
                'tax_responsibility_uuid' => (string) Str::uuid(),
                'is_employee' => true,
                'roles' => ['EMPLEADO', 'COORDINADOR_SEDE'],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Deja de ser empleado (pasa a solo afiliado): el rol gestionado se quita, el
        // rol de coordinador de sede no se toca (se gestiona desde usuarios y roles).
        app(ThirdPartyService::class)->updateThirdParty($tercero->uuid, [
            'is_employee' => false, 'is_affiliate' => true, 'roles' => ['AFILIADO'],
        ]);

        $usuario = $this->vinculadoDe('pedro.empleado@test.local');
        $this->assertFalse($usuario->hasRole('EMPLEADO'));
        $this->assertTrue($usuario->hasRole('AFILIADO'));
        $this->assertTrue($usuario->hasRole('COORDINADOR_SEDE'), 'el rol de coordinador se preservó');
    }

    public function test_un_tercero_no_puede_escalar_un_rol_reservado_desde_el_registro(): void
    {
        Role::findOrCreate('CONDUCTOR', 'api');
        Role::findOrCreate('SUPERADMIN', 'api');

        // Actor que no es SUPERADMIN (sin autenticar, como cualquier editor de terceros)
        // intenta colar SUPERADMIN en el campo roles: se ignora en silencio.
        Schema::disableForeignKeyConstraints();
        try {
            app(ThirdPartyService::class)->createThirdParty([
                'company_uuid' => $this->empresa, 'person_type' => 'NATURAL',
                'document_type_uuid' => (string) Str::uuid(), 'document_number' => '1090999901',
                'first_name' => 'OSCAR', 'last_name' => 'MENTIROSO', 'email' => 'oscar.escalado@test.local',
                'municipality_uuid' => (string) Str::uuid(), 'tax_regime' => '48',
                'tax_responsibility_uuid' => (string) Str::uuid(),
                'is_driver' => true,
                'roles' => ['CONDUCTOR', 'SUPERADMIN', 'ADMIN_EMPRESA'],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $usuario = $this->vinculadoDe('oscar.escalado@test.local');
        $this->assertTrue($usuario->hasRole('CONDUCTOR'));
        $this->assertFalse($usuario->hasRole('SUPERADMIN'), 'el rol reservado no se puede asignar por terceros');
        $this->assertFalse($usuario->hasRole('ADMIN_EMPRESA'), 'el rol reservado no se puede asignar por terceros');
    }

    public function test_un_superadmin_si_puede_asignar_un_rol_reservado_desde_el_registro(): void
    {
        Role::findOrCreate('CONDUCTOR', 'api');
        Role::findOrCreate('SUPERADMIN', 'api');
        Role::findOrCreate('ADMIN_EMPRESA', 'api');
        $actor = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Superadmin', 'email' => 'super.actor@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        $actor->assignRole(Role::findOrCreate('SUPERADMIN', 'api'));
        Sanctum::actingAs($actor, ['*']);

        Schema::disableForeignKeyConstraints();
        try {
            app(ThirdPartyService::class)->createThirdParty([
                'company_uuid' => $this->empresa, 'person_type' => 'NATURAL',
                'document_type_uuid' => (string) Str::uuid(), 'document_number' => '1090999902',
                'first_name' => 'GLORIA', 'last_name' => 'DAMAGE', 'email' => 'gloria.admin@test.local',
                'municipality_uuid' => (string) Str::uuid(), 'tax_regime' => '48',
                'tax_responsibility_uuid' => (string) Str::uuid(),
                'is_driver' => true,
                'roles' => ['CONDUCTOR', 'ADMIN_EMPRESA'],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $usuario = $this->vinculadoDe('gloria.admin@test.local');
        $this->assertTrue($usuario->hasRole('CONDUCTOR'));
        $this->assertTrue($usuario->hasRole('ADMIN_EMPRESA'), 'superadmin asigna el rol reservado');
    }
}
