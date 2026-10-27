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
 * Datos de identidad de la cédula de una persona natural en terceros
 * (issue #9): lugar y fecha de expedición, sexo, estado civil y grupo
 * sanguíneo. Obligatorios para NATURAL, no aplican a JURIDICA; la edición
 * parcial no borra los campos no enviados. Se prueba por HTTP (seam único
 * del spec): la capa de servicio no tiene lógica propia para estos campos.
 */
class TerceroDatosPersonaTest extends TestCase
{
    use RefreshDatabase, InsertaFilas;

    private string $empresa;

    private string $ciudad;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);
        $this->empresa = $this->crearEmpresa();
        $this->ciudad = $this->insertar('cities', ['name' => 'Buenaventura']);
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

    private function comoAdmin()
    {
        setPermissionsTeamId(null);

        if ($this->admin === null) {
            Schema::disableForeignKeyConstraints();
            $this->admin = User::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin Datos Persona',
                'email' => 'admin.datos.persona@test.local',
                'password' => Hash::make('secreto123'),
                'is_active' => true,
            ]);
            Schema::enableForeignKeyConstraints();

            $this->admin->assignRole(Role::findOrCreate('ADMIN_EMPRESA', 'api'));
            DB::table('company_user')->insert([
                'user_id' => $this->admin->id, 'company_uuid' => $this->empresa,
                'third_party_uuid' => null,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->admin, ['*']);

        return $this->withHeaders(['X-Company-UUID' => $this->empresa]);
    }

    /**
     * Catálogos y base de un cuerpo válida de tercero natural.
     */
    private function datosBase(): array
    {
        return [
            'company_uuid' => $this->empresa,
            'person_type' => 'NATURAL',
            'document_type_uuid' => $this->insertar('type_of_documents', ['name' => 'CC']),
            'document_number' => '10'.(string) random_int(10000000, 99999999),
            'first_name' => 'ANA',
            'last_name' => 'RESTREPO',
            'email' => 'ana.'.Str::random(6).'@tercero.test',
            'municipality_uuid' => $this->ciudad,
            'branch_uuid' => $this->insertar('branches', [
                'company_uuid' => $this->empresa,
                'name' => 'Sede '.Str::random(4),
                'address' => 'Calle 2',
                'municipality_uuid' => $this->ciudad,
            ]),
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $this->insertar('tax_responsibilities', ['code' => 'R-99-'.Str::upper(Str::random(4))]),
        ];
    }

    /**
     * Solo lectura: la thread global de permisos de Spatie se reinicia por
     * petición y el resto es fijo de la empresa del test.
     */
    private function uuidDe(string $tabla, string $columna = 'uuid'): string
    {
        return (string) DB::table($tabla)->value($columna);
    }

    /**
     * Campos rechazados en la respuesta 422: la lista de detalles trae
     * {field, messages, code}.
     */
    private function camposRechazados($respuesta): array
    {
        return collect($respuesta->json('error.details'))->pluck('field')->all();
    }

    /**
     * Mensajes de un campo rechazado en la respuesta 422.
     */
    private function mensajesDe($respuesta, string $campo): array
    {
        return collect($respuesta->json('error.details'))
            ->firstWhere('field', $campo)['messages'] ?? [];
    }

    public function test_crear_persona_natural_persiste_y_devuelve_los_datos_de_persona(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => $this->ciudad,
            'birth_date' => '1985-04-12',
            'id_card_issue_place_uuid' => $this->ciudad,
            'id_card_issue_date' => '2005-06-15',
            'sex' => 'F',
            'marital_status' => 'SOLTERO',
            'blood_type' => 'O+',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertCreated();

        $fila = DB::table('third_parties')
            ->where('document_number', $datos['document_number'])
            ->first();

        $this->assertSame($this->ciudad, $fila->birth_place_uuid);
        $this->assertSame('1985-04-12', (string) $fila->birth_date);
        $this->assertSame($this->ciudad, $fila->id_card_issue_place_uuid);
        $this->assertSame('2005-06-15', (string) $fila->id_card_issue_date);
        $this->assertSame('F', $fila->sex);
        $this->assertSame('SOLTERO', $fila->marital_status);
        $this->assertSame('O+', $fila->blood_type);

        // El registro devuelto expone los datos para la ficha y el formulario.
        $this->assertStringStartsWith('1985-04-12', (string) $r->json('data.birth_date'));
        $this->assertSame($this->ciudad, $r->json('data.birth_place_uuid'));
        $r->assertJsonFragment([
            'sex' => 'F',
            'marital_status' => 'SOLTERO',
            'blood_type' => 'O+',
        ]);
    }

    public function test_persona_juridica_se_guarda_sin_los_datos_de_persona(): void
    {
        $datos = $this->datosBase();
        $datos['person_type'] = 'JURIDICA';
        $datos['company_name'] = 'ACME SAS';

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertCreated();

        $fila = DB::table('third_parties')
            ->where('document_number', $datos['document_number'])
            ->first();

        $this->assertNull($fila->birth_place_uuid);
        $this->assertNull($fila->birth_date);
        $this->assertNull($fila->id_card_issue_place_uuid);
        $this->assertNull($fila->id_card_issue_date);
        $this->assertNull($fila->sex);
        $this->assertNull($fila->marital_status);
        $this->assertNull($fila->blood_type);
    }

    public function test_persona_natural_sin_los_datos_rechaza_con_mensajes_etiquetados(): void
    {
        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $this->datosBase());

        $r->assertStatus(422);

        $rechazados = $this->camposRechazados($r);

        $this->assertContains('birth_place_uuid', $rechazados);
        $this->assertContains('birth_date', $rechazados);
        $this->assertContains('id_card_issue_place_uuid', $rechazados);
        $this->assertContains('id_card_issue_date', $rechazados);
        $this->assertContains('sex', $rechazados);
        $this->assertContains('marital_status', $rechazados);
        $this->assertContains('blood_type', $rechazados);
        $this->assertStringContainsString('personas naturales', implode(' ', $this->mensajesDe($r, 'sex')));
    }

    public function test_valores_fuera_de_las_listas_rechazados(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => $this->ciudad,
            'birth_date' => '1990-01-01',
            'id_card_issue_place_uuid' => $this->ciudad,
            'id_card_issue_date' => '2005-06-15',
            'sex' => 'X',
            'marital_status' => 'SEPARADO_FACTO',
            'blood_type' => 'C+',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertStatus(422);

        $rechazados = $this->camposRechazados($r);

        $this->assertContains('sex', $rechazados);
        $this->assertContains('marital_status', $rechazados);
        $this->assertContains('blood_type', $rechazados);
    }

    public function test_la_fecha_de_expedicion_futura_rechazada(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => $this->ciudad,
            'birth_date' => '1990-01-01',
            'id_card_issue_place_uuid' => $this->ciudad,
            'id_card_issue_date' => now()->addDay()->toDateString(),
            'sex' => 'M',
            'marital_status' => 'CASADO',
            'blood_type' => 'A-',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertStatus(422);
        $this->assertStringContainsString(
            'no puede ser posterior a hoy',
            implode(' ', $this->mensajesDe($r, 'id_card_issue_date'))
        );
    }

    public function test_la_fecha_de_nacimiento_debe_ser_anterior_a_hoy(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => $this->ciudad,
            'birth_date' => now()->toDateString(),
            'id_card_issue_place_uuid' => $this->ciudad,
            'id_card_issue_date' => '2010-01-01',
            'sex' => 'M',
            'marital_status' => 'CASADO',
            'blood_type' => 'A-',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertStatus(422);
        $this->assertStringContainsString(
            'debe ser anterior a hoy',
            implode(' ', $this->mensajesDe($r, 'birth_date'))
        );
    }

    public function test_el_lugar_de_nacimiento_debe_ser_una_ciudad_existente(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => (string) Str::uuid(),
            'birth_date' => '1990-01-01',
            'id_card_issue_place_uuid' => $this->ciudad,
            'id_card_issue_date' => '2005-06-15',
            'sex' => 'M',
            'marital_status' => 'CASADO',
            'blood_type' => 'A-',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertStatus(422);
        $this->assertContains('birth_place_uuid', $this->camposRechazados($r));
    }

    public function test_el_lugar_de_expedicion_debe_ser_una_ciudad_existente(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => $this->ciudad,
            'birth_date' => '1990-01-01',
            'id_card_issue_place_uuid' => (string) Str::uuid(),
            'id_card_issue_date' => '2005-06-15',
            'sex' => 'M',
            'marital_status' => 'CASADO',
            'blood_type' => 'A-',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);

        $r->assertStatus(422);
        $this->assertContains('id_card_issue_place_uuid', $this->camposRechazados($r));
    }

    public function test_la_edicion_actualiza_y_la_parcial_no_borra_los_datos(): void
    {
        $datos = $this->datosBase() + [
            'birth_place_uuid' => $this->ciudad,
            'birth_date' => '1980-08-20',
            'id_card_issue_place_uuid' => $this->ciudad,
            'id_card_issue_date' => '1998-02-11',
            'sex' => 'M',
            'marital_status' => 'SOLTERO',
            'blood_type' => 'B+',
        ];

        $r = $this->comoAdmin()->postJson('/api/v1/third-parties', $datos);
        $r->assertCreated();

        $uuid = DB::table('third_parties')
            ->where('document_number', $datos['document_number'])
            ->value('uuid');

        // Edición parcial (sin person_type ni datos de persona): las obligatorias
        // del flujo (sucursal, correo, municipio) van; los datos no se
        // tocan ni se borran.
        $this->comoAdmin()->putJson('/api/v1/third-parties/'.$uuid, [
            'branch_uuid' => $datos['branch_uuid'],
            'email' => $datos['email'],
            'municipality_uuid' => $this->ciudad,
            'tax_regime' => '48',
            'tax_responsibility_uuid' => $datos['tax_responsibility_uuid'],
            'phone' => '5551234',
        ])->assertOk();

        $fila = DB::table('third_parties')->where('uuid', $uuid)->first();

        $this->assertSame($this->ciudad, $fila->birth_place_uuid);
        $this->assertSame('1980-08-20', (string) $fila->birth_date);
        $this->assertSame($this->ciudad, $fila->id_card_issue_place_uuid);
        $this->assertSame('1998-02-11', (string) $fila->id_card_issue_date);
        $this->assertSame('M', $fila->sex);
        $this->assertSame('SOLTERO', $fila->marital_status);
        $this->assertSame('B+', $fila->blood_type);

        // Edición completa: actualiza los datos de persona.
        $otraCiudad = $this->insertar('cities', ['name' => 'Pasto']);
        $this->comoAdmin()->putJson('/api/v1/third-parties/'.$uuid, [
            'person_type' => 'NATURAL',
            'branch_uuid' => $datos['branch_uuid'],
            'birth_place_uuid' => $otraCiudad,
            'birth_date' => '1982-12-05',
            'id_card_issue_place_uuid' => $otraCiudad,
            'id_card_issue_date' => '2001-10-30',
            'sex' => 'M',
            'marital_status' => 'CASADO',
            'blood_type' => 'AB-',
        ])->assertOk();

        $fila = DB::table('third_parties')->where('uuid', $uuid)->first();

        $this->assertSame($otraCiudad, $fila->birth_place_uuid);
        $this->assertSame('1982-12-05', (string) $fila->birth_date);
        $this->assertSame($otraCiudad, $fila->id_card_issue_place_uuid);
        $this->assertSame('2001-10-30', (string) $fila->id_card_issue_date);
        $this->assertSame('CASADO', $fila->marital_status);
        $this->assertSame('AB-', $fila->blood_type);
    }
}
