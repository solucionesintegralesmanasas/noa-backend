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
use Spatie\Permission\Models\Permission;
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
        $this->padre = $this->insertar('procedures', ['company_uuid' => $this->empresa, 'procedure_code' => 'EXP-PADRE-'.Str::random(6)]);
        $this->hijo = $this->insertar('procedures', ['company_uuid' => $this->empresa, 'parent_procedure_uuid' => $this->padre, 'procedure_code' => 'EXP-HIJO-'.Str::random(6)]);
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
        $rol = Role::findOrCreate('ADMIN_EMPRESA', 'api');
        foreach (['index', 'create', 'update'] as $accion) {
            $rol->givePermissionTo(Permission::findOrCreate("radicacion_to.{$accion}", 'api'));
        }
        $user->assignRole($rol);

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

    public function test_generar_txt_mantiene_formato_run_t(): void
    {
        // Configuración inicial: empresa con datos completos, municipio con código DANE
        $ciudad = $this->insertar('cities', ['dane_code' => 11001]);
        $this->empresa = $this->insertar('companies', [
            'business_name' => 'TRANSPORTES ESPECIALES SIN BARRERAS S.A.S',
            'address' => 'Carrera 14 No. 21-12 Chinú',
            'phone' => '3234513620',
            'email' => 'transespecialessinbarreras@gmail.com',
            'document_number' => '901797712',
            'municipality_uuid' => $ciudad,
            'legal_representative_name' => 'ALEXANDER',
            'legal_representative_last_name' => 'MIGUEL CARDENAS NARANJO',
            'legal_representative_document_issue_date' => '2026-09-02',
            'is_active' => true,
        ]);

        // Crear expediente padre e hijo TO
        $padre = $this->insertar('procedures', [
            'company_uuid' => $this->empresa,
            'procedure_type' => 'INCLUSION_DE_POLIZAS',
            'procedure_code' => 'EXP-TO-TEST',
            'status' => 'COMPLETADO',
        ]);
        $hijoTo = $this->insertar('procedures', [
            'company_uuid' => $this->empresa,
            'parent_procedure_uuid' => $padre,
            'procedure_type' => 'TARJETA_DE_OPERACION',
            'procedure_code' => 'EXP-TO-TEST-TO',
            'status' => 'COMPLETADO',
        ]);

        // Vehículo con placa y clase
        $clase = $this->insertar('vehicle_class', ['class_code_class' => '7', 'description' => 'CAMIONETA']);
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa,
            'vehicle_license_plate' => 'SZV872',
            'vehicle_class_uuid' => $clase,
        ]);
        DB::table('procedures')->where('uuid', $hijoTo)->update(['vehicle_uuid' => $vehiculo]);

        // Tercero/proprietario asociado al vehículo
        $tercero = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'company_name' => 'HUGO ALEXANDER MOLINA',
            'document_number' => '98479678',
            'address' => 'CLL 24C # 9B - 43 URBANIZACIÓN LA BUCARAMANGA',
            'phone' => '3234513622',
            'email' => 'transespecialessinbarreras@gmail.com',
            'is_active' => true,
        ]);
        DB::table('vehicles')->where('uuid', $vehiculo)->update(['third_party_uuid' => $tercero]);

        // Contrato FleetServiceContract
        $contrato = $this->insertar('fleet_service_contracts', [
            'company_uuid' => $this->empresa,
            'procedure_uuid' => $hijoTo,
            'vehicle_uuid' => $vehiculo,
            'third_party_uuid' => $tercero,
            'type_of_action' => 'C',
            'issue_date' => '2026-09-02',
            'start_date' => '2026-09-02',
            'end_date' => '2028-09-02',
            'duration' => 730,
            'contract_type' => '2',
            'contract_number' => 'FC-001',
        ]);

        // Firma del contrato de administración de flotas
        $this->insertar('contract_signatures', [
            'company_uuid' => $this->empresa,
            'contract_origin' => 'ADMIN_FLOTA',
            'contract_uuid' => $contrato,
            'signer_role' => 'REP_LEGAL',
            'signer_name' => 'ALEXANDER MIGUEL CARDENAS NARANJO',
            'signer_document' => '12345678',
            'token_hash' => hash('sha256', 'admin-flota-token-'.time()),
            'status' => 'FIRMADO',
            'signed_at' => now(),
        ]);

        // Contrato de prestación de servicios (requerido para validación)
        $contratoPrestacion = $this->insertar('service_provision_contracts', [
            'company_uuid' => $this->empresa,
            'procedure_uuid' => $hijoTo,
            'vehicle_uuid' => $vehiculo,
            'contract_number' => 'SP-001',
            'issue_date' => '2026-09-02',
            'start_date' => '2026-09-02',
            'end_date' => '2028-09-02',
            'duration' => 730,
            'status' => 'PENDIENTE_FIRMA',
        ]);

        // Firma del contrato de prestación (requerido para validar requisitos)
        $this->insertar('contract_signatures', [
            'company_uuid' => $this->empresa,
            'contract_origin' => 'PRESTACION',
            'contract_uuid' => $contratoPrestacion,
            'signer_role' => 'REP_LEGAL',
            'signer_name' => 'ALEXANDER MIGUEL CARDENAS NARANJO',
            'signer_document' => '12345678',
            'token_hash' => hash('sha256', 'prestacion-token-'.time()),
            'status' => 'FIRMADO',
            'signed_at' => now(),
        ]);

        // Autenticar y llamar a generarTxt
        Sanctum::actingAs($this->usuario(), ['*']);
        $respuesta = $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$hijoTo}/txt", ['origin' => 'ADMIN_FLOTA']);
        $respuesta->assertCreated();

        // Obtener el lote generado
        $lote = \App\Models\RuntTxtBatch::where('procedure_uuid', $hijoTo)
            ->where('origin', 'ADMIN_FLOTA')
            ->firstOrFail();

        $lineas = explode("\r\n", trim($lote->content));
        $this->assertCount(2, $lineas, 'El TXT debe tener exactamente 2 líneas: cabecera y datos');

        // Verificar línea cabecera (3 campos)
        $cabecera = explode('|', $lineas[0]);
        $this->assertCount(3, $cabecera);
        $this->assertSame('11001', $cabecera[0], 'Código DANE del municipio');
        $this->assertSame('901797712', $cabecera[2], 'NIT de la empresa sin dígito');

        // Verificar línea de datos (22 campos)
        $datos = explode('|', $lineas[1]);
        $this->assertCount(22, $datos, 'La línea de datos debe tener exactamente 22 campos');

        // Validar cada campo
        $this->assertSame('11001', $datos[0], 'Código DANE municipio');
        $this->assertSame('TRANSPORTES ESPECIALES SIN BARRERAS S.A.S', $datos[1], 'Razón social empresa');
        $this->assertSame('Carrera 14 No. 21-12 Chinú', $datos[2], 'Dirección empresa');
        $this->assertSame('3234513620', $datos[3], 'Teléfono empresa');
        $this->assertSame('transespecialessinbarreras@gmail.com', $datos[4], 'Correo empresa');
        $this->assertSame('ALEXANDER MIGUEL CARDENAS NARANJO', $datos[5], 'Nombre representante legal');
        $this->assertSame('20260902', $datos[6], 'Fecha expedición documento rep. legal');
        $this->assertSame('2', $datos[7], 'Tipo contrato (2 = prestación)');
        $this->assertSame('20260902', $datos[8], 'Fecha inicio contrato');
        $this->assertSame('20280902', $datos[9], 'Fecha fin contrato');
        $this->assertSame('SZV872', $datos[10], 'Placa vehículo');
        $this->assertSame('', $datos[11], 'Código clase vehículo (no configurado)');
        $this->assertSame('C', $datos[12], 'Tipo acción (Creación)');
        $this->assertSame('98479678', $datos[13], 'NIT propietario');
        $this->assertSame('HUGO ALEXANDER MOLINA', $datos[14], 'Nombre propietario');
        $this->assertSame('CLL 24C # 9B - 43 URBANIZACIÓN LA BUCARAMANGA', $datos[15], 'Dirección propietario');
        $this->assertSame('3234513622', $datos[16], 'Teléfono propietario');
        $this->assertSame('transespecialessinbarreras@gmail.com', $datos[17], 'Correo propietario');
        $this->assertSame('20260902', $datos[18], 'Fecha contrato');
        $this->assertSame('730', $datos[19], 'Duración en días');
        $this->assertSame('0050', $datos[20], 'Código fijo');
        $this->assertSame('S', $datos[21], 'Estado radicado');
    }
}
