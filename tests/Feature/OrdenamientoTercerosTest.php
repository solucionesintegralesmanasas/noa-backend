<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ThirdParties\ThirdPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\TestCase;

/**
 * Ordenamiento server-side del listado de terceros (contrato `sort_by`/`sort_order`).
 */
class OrdenamientoTercerosTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private const BASE = '/api/v1/third-parties';

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['authorization.enforce' => false]);
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa orden terceros', 'is_active' => true]);
    }

    private function tercero(string $documento, array $extra = []): string
    {
        return $this->insertar('third_parties', $extra + [
            'company_uuid' => $this->empresa,
            'document_number' => $documento,
            'trade_name' => 'Tercero '.$documento,
            'email' => 't'.$documento.'@test.local',
            'is_active' => true,
        ]);
    }

    private function listar(?string $sortBy, ?string $sortOrder = 'asc', int $perPage = 10, int $page = 1)
    {
        $servicio = app(ThirdPartyService::class);
        $paginador = $servicio->getAllThirdPartiesWithPagination(
            perPage: $perPage,
            page: $page,
            search: '',
            companyUuid: $this->empresa,
            type: null,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        return $paginador->items();
    }

    public function test_ordena_por_document_number_asc_y_desc(): void
    {
        $this->tercero('100');
        $this->tercero('300');
        $this->tercero('200');

        $asc = collect($this->listar('document_number', 'asc'))->pluck('document_number')->all();
        $desc = collect($this->listar('document_number', 'desc'))->pluck('document_number')->all();

        $this->assertSame(['100', '200', '300'], $asc);
        $this->assertSame(['300', '200', '100'], $desc);
    }

    public function test_ordena_por_trade_name(): void
    {
        $this->tercero('1', ['trade_name' => 'Zacarias']);
        $this->tercero('2', ['trade_name' => 'Alberto']);
        $this->tercero('3', ['trade_name' => 'Carlos']);

        $asc = collect($this->listar('trade_name', 'asc'))->pluck('trade_name')->all();

        $this->assertSame(['Alberto', 'Carlos', 'Zacarias'], $asc);
    }

    public function test_ignora_campo_fuera_de_la_lista_blanca(): void
    {
        $this->tercero('100');
        $this->tercero('200');

        // sql_injection_attempt no está en $sortableFields; no debe romperse ni lanzar excepción
        $items = $this->listar('malicious_field', 'asc');
        $this->assertCount(2, $items);
    }

    public function test_endpoint_acepta_sort_by_y_sort_order(): void
    {
        $this->tercero('999');
        $this->tercero('111');

        $usuario = $this->usuarioAdministrador();

        Sanctum::actingAs($usuario, ['*']);
        $respuesta = $this->withHeaders(['X-Company-UUID' => $this->empresa])
            ->getJson(self::BASE.'?company_uuid='.$this->empresa.'&sort_by=document_number&sort_order=desc');

        $respuesta->assertOk();
        $docs = collect($respuesta->json('data.data'))->pluck('document_number')->values()->all();
        $this->assertSame(['999', '111'], $docs);
    }

    private function usuarioAdministrador(): User
    {
        Permission::findOrCreate('third_parties.index', 'api');

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin terceros',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
        ]);

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('ADMIN_EMPRESA', 'api'));
        $user->givePermissionTo(Permission::all());

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $this->empresa,
            'third_party_uuid' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }
}
