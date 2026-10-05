<?php

declare(strict_types=1);

namespace Tests\Feature;

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

/**
 * Regla: un conductor tiene una sola licencia vigente; solo se crea una
 * nueva ACTIVA cuando la anterior ya está vencida (por estado o por fecha).
 * Al crearla, la anterior ACTIVA pasa a VENCIDA sola.
 */
class LicenciaUnicaVigenteTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $conductor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Licencia única', 'is_active' => true]);
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
            'name' => 'Prueba Licencias',
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

    /** @return array<string, string> */
    private function cabecera(): array
    {
        return ['X-Company-UUID' => $this->empresa];
    }

    private function crear(array $datos): string
    {
        return $this->insertar('driver_licenses', $datos + [
            'company_uuid' => $this->empresa,
            'third_party_uuid' => $this->conductor,
            'number' => 'N'.Str::random(6),
            'category' => 'C2',
            'issue_date' => '2022-01-01',
            'expiration_date' => '2030-01-01',
            'status' => 'ACTIVA',
        ]);
    }

    /** @return mixed */
    private function postLicencia(array $licencia)
    {
        return $this->postJson(
            '/api/v1/fleet-management/driver-licenses',
            ['driver_license' => $licencia],
            $this->cabecera()
        );
    }

    private function base(string $estado = 'ACTIVA', string $vencimiento = '2030-01-01'): array
    {
        return [
            'company_uuid' => $this->empresa,
            'third_party_uuid' => $this->conductor,
            'number' => 'N'.Str::random(6),
            'category' => 'C2',
            'issue_date' => '2022-01-01',
            'expiration_date' => $vencimiento,
            'status' => $estado,
        ];
    }

    public function test_no_crea_activa_si_hay_otra_vigente(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $this->crear([]);

        $respuesta = $this->postLicencia($this->base());

        $respuesta->assertStatus(422);
        $this->assertDatabaseCount('driver_licenses', 1);
    }

    public function test_crea_activa_si_la_anterior_esta_vencida_por_estado(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $this->crear(['status' => 'VENCIDA', 'expiration_date' => '2024-01-01']);

        $respuesta = $this->postLicencia($this->base());

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('driver_licenses', 2);
    }

    public function test_crea_activa_si_la_anterior_vencio_por_fecha_y_la_marca_vencida(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $vieja = $this->crear(['status' => 'ACTIVA', 'expiration_date' => '2020-01-01']);

        $respuesta = $this->postLicencia($this->base());

        $respuesta->assertStatus(201);
        $this->assertSame(
            'VENCIDA',
            DB::table('driver_licenses')->where('uuid', $vieja)->value('status')
        );
    }

    public function test_no_activa_una_vencida_si_hay_otra_vigente(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $this->crear([]);
        $otra = $this->crear(['status' => 'VENCIDA', 'expiration_date' => '2023-01-01', 'number' => 'N'.Str::random(6)]);

        $respuesta = $this->putJson(
            '/api/v1/fleet-management/driver-licenses/'.$otra,
            ['driver_license' => ['status' => 'ACTIVA']],
            $this->cabecera()
        );

        $respuesta->assertStatus(422);
    }

    public function test_editar_la_vigente_sin_cambiar_estado_sigue_permitido(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $uuid = $this->crear([]);

        $respuesta = $this->putJson(
            '/api/v1/fleet-management/driver-licenses/'.$uuid,
            ['driver_license' => ['restrictions' => 'Ninguna']],
            $this->cabecera()
        );

        $respuesta->assertStatus(200);
    }

    public function test_crear_historica_vencida_con_vigente_activa_sigue_permitido(): void
    {
        Sanctum::actingAs($this->usuario(), ['*']);
        $this->crear([]);

        $respuesta = $this->postLicencia($this->base('VENCIDA', '2020-01-01'));

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('driver_licenses', 2);
    }
}
