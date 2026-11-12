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
        $tipoDocumento = $this->insertar('type_of_documents', [
            'name' => 'Cédula de ciudadanía',
            'prefix' => 'CC',
            'status' => 1,
        ]);
        $tercero = $this->insertar('third_parties', [
            'company_uuid' => $this->empresa,
            'company_name' => 'HUGO ALEXANDER MOLINA',
            'document_number' => '98479678',
            'document_type_uuid' => $tipoDocumento,
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
            'contract_type' => '1',
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

        // Configuración de la empresa contratante para el TXT de prestación.
        $this->insertar('system_configuration', [
            'company_uuid' => $this->empresa,
            'contract_company_name' => 'FUNDACION COLOMBIANA NUEVO SIGLO',
            'contract_company_address' => 'CARRERA 13 NO. 24-66',
            'contract_company_phone' => '3002978382',
            'contract_company_email' => 'fundacolsincelejo@yahoo.es',
            'contract_company_rep_name' => 'LUIS TADEO',
            'contract_company_rep_last_name' => 'MACHADO ROMERO',
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
        $this->assertSame('1', $cabecera[0], 'Consecutivo por empresa (inicia en 1)');
        $this->assertSame('20260902', $cabecera[1], 'Fecha de emisión del contrato');
        $this->assertSame('901797712', $cabecera[2], 'NIT de la empresa sin dígito');

        // Verificar línea de datos (22 campos del formato de vinculación)
        $datos = explode('|', $lineas[1]);
        $this->assertCount(22, $datos, 'La línea de datos debe tener exactamente 22 campos');

        // Validar cada campo
        $this->assertSame('1', $datos[0], 'Consecutivo por empresa');
        $this->assertSame('TRANSPORTES ESPECIALES SIN BARRERAS S.A.S', $datos[1], 'Razón social empresa');
        $this->assertSame('Carrera 14 No. 21-12 Chinú', $datos[2], 'Dirección empresa');
        $this->assertSame('3234513620', $datos[3], 'Teléfono empresa');
        $this->assertSame('transespecialessinbarreras@gmail.com', $datos[4], 'Correo empresa');
        $this->assertSame('C', $datos[5], 'Tipo de acción (Creación)');
        $this->assertSame('20260902', $datos[6], 'Fecha de emisión del contrato');
        $this->assertSame('ALEXANDER MIGUEL CARDENAS NARANJO', $datos[7], 'Nombre representante legal');
        $this->assertSame('20260902', $datos[8], 'Fecha inicio contrato');
        $this->assertSame('20280902', $datos[9], 'Fecha fin contrato');
        $this->assertSame('24', $datos[10], 'Duración en meses');
        $this->assertSame('SZV872', $datos[11], 'Placa vehículo');
        $this->assertSame('7', $datos[12], 'Código clase vehículo');
        $this->assertSame('C', $datos[13], 'Tipo documento propietario (CC → C)');
        $this->assertSame('98479678', $datos[14], 'Documento propietario');
        $this->assertSame('HUGO ALEXANDER MOLINA', $datos[15], 'Nombre propietario');
        $this->assertSame('Carrera 14 No. 21-12 Chinú', $datos[16], 'Dirección de la empresa de transportes');
        $this->assertSame('3234513620', $datos[17], 'Teléfono de la empresa de transportes');
        $this->assertSame('transespecialessinbarreras@gmail.com', $datos[18], 'Correo de la empresa de transportes');
        $this->assertSame('1', $datos[19], 'Tipo contrato (1 = vinculación de flota)');
        $this->assertSame('FC-001', $datos[20], 'Número del contrato');
        $this->assertSame('S', $datos[21], 'Estado por defecto (único fijo)');

        // Volver a generar no crea otro TXT: se reutiliza el del contrato.
        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$hijoTo}/txt", ['origin' => 'ADMIN_FLOTA'])
            ->assertCreated();
        $this->assertSame(
            1,
            \App\Models\RuntTxtBatch::where('procedure_uuid', $hijoTo)->where('origin', 'ADMIN_FLOTA')->count(),
            'Un solo TXT por contrato'
        );

        // El TXT de prestación debe salir de service_provision_contracts, no de flota.
        $this->withHeader('X-Company-UUID', $this->empresa)
            ->postJson("/api/v1/procedure/radicacion/{$hijoTo}/txt", ['origin' => 'PRESTACION'])
            ->assertCreated();

        $lotePrestacion = \App\Models\RuntTxtBatch::where('procedure_uuid', $hijoTo)
            ->where('origin', 'PRESTACION')
            ->firstOrFail();
        $datosPrestacion = explode('|', explode("\r\n", trim($lotePrestacion->content))[1]);
        $this->assertCount(22, $datosPrestacion, 'El TXT de prestación también tiene 22 campos');
        $this->assertSame('2', $datosPrestacion[0], 'Consecutivo de la empresa (segundo TXT)');
        $this->assertSame('SP-001', $datosPrestacion[20], 'Número del contrato de prestación');
        $this->assertSame('FUNDACION COLOMBIANA NUEVO SIGLO', $datosPrestacion[1], 'Empresa contratante configurada');
        $this->assertSame('CARRERA 13 NO. 24-66', $datosPrestacion[2], 'Dirección de la contratante');
        $this->assertSame('3002978382', $datosPrestacion[3], 'Teléfono de la contratante');
        $this->assertSame('fundacolsincelejo@yahoo.es', $datosPrestacion[4], 'Correo de la contratante');
        $this->assertSame('LUIS TADEO MACHADO ROMERO', $datosPrestacion[7], 'Representante de la contratante');
        $this->assertSame('Carrera 14 No. 21-12 Chinú', $datosPrestacion[16], 'Campos 17-19 siempre de la empresa de transportes');
        $this->assertSame('3234513620', $datosPrestacion[17], 'Teléfono de la empresa de transportes');
        $this->assertSame('transespecialessinbarreras@gmail.com', $datosPrestacion[18], 'Correo de la empresa de transportes');
        $this->assertSame('2', $datosPrestacion[19], 'Tipo contrato (2 = prestación)');
    }
}
