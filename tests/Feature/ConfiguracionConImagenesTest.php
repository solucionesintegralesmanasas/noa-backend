<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-006: la configuración del sistema con imágenes (logo del ministerio, de la superintendencia, membrete)
 * respondía con una recursión archivo → configuración → archivo (~43.000 consultas, >500 MB) y mataba el worker.
 * Solo fallaba con al menos un archivo adjunto: estas pruebas siembran siempre uno.
 */
#[Group('perf')]
class ConfiguracionConImagenesTest extends TestCase
{
    use RefreshDatabase, InsertaFilas, PresupuestoConsultas;

    private string $empresa;

    private SystemConfiguration $config;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);

        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa con membrete', 'document_number' => '901797712', 'is_active' => true]);
        $uuid = $this->insertar('system_configuration', ['company_uuid' => $this->empresa]);
        $this->config = SystemConfiguration::withoutGlobalScopes()->where('uuid', $uuid)->firstOrFail();
    }

    private function adjuntar(string $coleccion): void
    {
        $this->config->addFile(UploadedFile::fake()->image($coleccion.'.png'), $coleccion);
    }

    private function consultar()
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Super', 'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'), 'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();
        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate('SUPERADMIN', 'api'));
        Sanctum::actingAs($user, ['*']);

        return $this->getJson('/api/v1/settings/system-configurations/company/'.$this->empresa);
    }

    public function test_la_configuracion_con_membrete_responde_200_con_las_tres_urls(): void
    {
        $this->adjuntar('LETTERHEAD');

        $r = $this->consultar();

        $r->assertOk();
        $this->assertStringContainsString('companies/901797712/LETTERHEAD/', $r->json('data.letterhead_url'));
        $this->assertArrayHasKey('ministry_logo_url', $r->json('data'));
        $this->assertArrayHasKey('super_logo_url', $r->json('data'));
        $this->assertEmpty($r->json('data.ministry_logo_url'), 'Sin archivo en la colección la URL queda vacía');
    }

    public function test_la_respuesta_no_anida_los_archivos_ni_el_modelo_duenio(): void
    {
        $this->adjuntar('LETTERHEAD');
        $this->adjuntar('MINISTRY_LOGO');

        $datos = $this->consultar()->assertOk()->json('data');

        $this->assertArrayNotHasKey('media', $datos, 'La relación de archivos no se serializa');
        $this->assertStringNotContainsString('"model"', json_encode($datos), 'El modelo dueño no debe ir dentro de un archivo');
        $this->assertStringContainsString('MINISTRY_LOGO', $datos['ministry_logo_url']);
        $this->assertStringContainsString('LETTERHEAD', $datos['letterhead_url']);
    }

    public function test_un_archivo_borrado_del_disco_degrada_sin_tumbar_la_peticion(): void
    {
        $this->adjuntar('LETTERHEAD');
        Storage::disk('public')->deleteDirectory('companies');

        $this->consultar()->assertOk()->assertJsonPath('data.uuid', $this->config->uuid);
    }

    public function test_sin_configuracion_responde_200_con_null(): void
    {
        DB::table('system_configuration')->where('company_uuid', $this->empresa)->delete();

        $this->consultar()->assertOk()->assertJsonPath('data', null);
    }

    public function test_la_configuracion_con_imagenes_tiene_un_presupuesto_de_consultas(): void
    {
        $this->adjuntar('LETTERHEAD');
        $medir = fn () => $this->consultar()->assertOk();

        $medir(); // calentamiento: roles y permisos del arranque
        $conUno = $this->contarConsultas($medir);
        $this->adjuntar('MINISTRY_LOGO');
        $this->adjuntar('SUPER_LOGO');
        $conTres = $this->contarConsultas($medir);

        // Medido: 18 con 1 archivo y 30 con 3 (máximo posible: tres colecciones de un solo archivo). Antes: ~43.000 encadenadas.
        $this->assertLessThanOrEqual(18, $conUno, "Con 1 archivo hizo $conUno consultas");
        $this->assertLessThanOrEqual(30, $conTres, "Con 3 archivos hizo $conTres consultas");
    }
}
