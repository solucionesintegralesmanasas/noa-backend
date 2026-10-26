<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * El «Afiliado Asignado (Propietario)» de un conductor no es columna del
 * tercero: vive en owners_drivers, unida a su licencia por driver_license_uuid.
 * El formulario de edición lo precarga desde el listado
 * /fleet-management/driver-licenses/list?third_party_uuid=<conductor>, así que
 * el listado debe entregar la relación con el uuid del afiliado.
 */
class OwnerDriverEnListadoLicenciasTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $afiliado;

    private string $conductor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Owner driver visible', 'is_active' => true]);
        $this->afiliado = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'is_affiliate' => true,
            'is_active' => true,
        ]);
        $this->conductor = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'is_driver' => true,
            'is_active' => true,
        ]);
    }

    private function usuario(): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prueba Owner Driver',
            'email' => Str::random(8).'@test.local',
            'password' => bcrypt('secreto123'),
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

    /** Licencia del conductor + su owner_driver apuntando al afiliado. */
    private function licenciaConOwner(): string
    {
        $licencia = $this->insertar('driver_licenses', [
            'company_uuid' => $this->empresa,
            'third_party_uuid' => $this->conductor,
            'number' => 'LIB-1',
            'category' => 'C2',
            'issue_date' => '2022-01-01',
            'expiration_date' => '2030-01-01',
            'status' => 'ACTIVA',
        ]);
        $this->insertar('owners_drivers', [
            'company_uuid' => $this->empresa,
            'driver_license_uuid' => $licencia,
            'third_party_uuid' => $this->afiliado,
        ]);

        return $licencia;
    }

    /**
     * La relación del propietario viaja en la licencia con el uuid del afiliado,
     * en cualquiera de las dos claves posibles (owner_driver o ownerDriver).
     */
    public function test_el_listado_de_licencias_entrega_el_owner_driver_del_afiliado(): void
    {
        $this->licenciaConOwner();

        Sanctum::actingAs($this->usuario());

        $response = $this->getJson(
            '/api/v1/fleet-management/driver-licenses/list?third_party_uuid='.$this->conductor,
            ['X-Company-UUID' => $this->empresa]
        );
        $response->assertOk()->assertJsonCount(1, 'data');

        $licencia = $response->json('data.0');
        $owner = $licencia['owner_driver'] ?? $licencia['ownerDriver'] ?? null;
        $this->assertNotNull($owner, 'La licencia debe traer la relación del propietario');
        $this->assertSame($this->afiliado, $owner['third_party_uuid'] ?? $owner['thirdPartyUuid'] ?? null,
            'El owner_driver debe exponer el uuid del afiliado que precarga el formulario');
    }
}
