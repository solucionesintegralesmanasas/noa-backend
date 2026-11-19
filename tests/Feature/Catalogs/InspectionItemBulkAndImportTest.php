<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionItemBulkAndImportTest extends TestCase
{
    use RefreshDatabase;

    private string $companyUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyUuid = $this->createCompany();
        $admin = $this->createUser('ADMIN_EMPRESA', $this->companyUuid);
        Sanctum::actingAs($admin, ['*']);
    }

    private function createCompany(): string
    {
        $uuid = (string) Str::uuid();
        $filler = (string) Str::uuid();

        Schema::disableForeignKeyConstraints();
        DB::table('companies')->insert([
            'uuid' => $uuid,
            'person_type' => 'PERSONA JURIDICA',
            'type_of_company' => 'PRIVADO',
            'document_type_uuid' => $filler,
            'document_number' => (string) random_int(100000000, 999999999),
            'business_name' => 'Empresa Inspección Test',
            'municipality_uuid' => $filler,
            'address' => 'Calle 10 # 20-30',
            'email' => Str::random(6).'@empresa.test',
            'tax_regime_uuid' => $filler,
            'web_page' => 'https://empresa.test',
            'legal_representative_name' => 'Rep',
            'legal_representative_last_name' => 'Test',
            'legal_representative_document_type' => 'CC',
            'legal_representative_document_number' => '123456',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }

    private function createUser(string $role, string $companyUuid): User
    {
        Schema::disableForeignKeyConstraints();
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Test',
            'email' => Str::random(8).'@test.local',
            'password' => Hash::make('secreto123'),
            'is_active' => true,
        ]);
        Schema::enableForeignKeyConstraints();

        setPermissionsTeamId(null);
        $user->assignRole(Role::findOrCreate($role, 'api'));

        DB::table('company_user')->insert([
            'user_id' => $user->id,
            'company_uuid' => $companyUuid,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function headers(): array
    {
        return ['X-Company-UUID' => $this->companyUuid];
    }

    public function test_puede_listar_y_crear_categorias_de_inspeccion(): void
    {
        $listResponse = $this->getJson('/api/v1/catalogs/inspection-items/categories', $this->headers());
        $listResponse->assertOk();
        $codes = collect($listResponse->json('data'))->pluck('code')->all();
        $this->assertContains('DOCUMENTOS', $codes);

        $createResponse = $this->postJson('/api/v1/catalogs/inspection-items/categories', [
            'name' => 'Sistema Eléctrico',
            'description' => 'Batería, alternador y cableado',
        ], $this->headers());

        $createResponse->assertStatus(201);
        $this->assertSame('SISTEMA_ELECTRICO', $createResponse->json('data.code'));
        $this->assertDatabaseHas('inspection_categories', [
            'code' => 'SISTEMA_ELECTRICO',
            'name' => 'Sistema Eléctrico',
        ]);
    }

    public function test_creacion_masiva_dinamica_omite_duplicados_y_crea_items(): void
    {
        $first = $this->postJson('/api/v1/catalogs/inspection-items/bulk', [
            'category' => 'Sistema Eléctrico',
            'items' => [
                ['item_name' => 'Estado de la batería', 'description' => 'Bornes limpios y carga'],
                ['item_name' => 'Alternador', 'description' => null],
            ],
        ], $this->headers());

        $first->assertStatus(201);
        $this->assertSame(2, $first->json('data.created_count'));
        $this->assertSame(0, $first->json('data.skipped_count'));
        $this->assertDatabaseHas('inspection_categories', ['code' => 'SISTEMA_ELECTRICO']);
        $this->assertDatabaseHas('inspection_items', [
            'category' => 'SISTEMA_ELECTRICO',
            'item_name' => 'Estado de la batería',
        ]);

        $second = $this->postJson('/api/v1/catalogs/inspection-items/bulk', [
            'category' => 'SISTEMA_ELECTRICO',
            'items' => [
                ['item_name' => 'estado de la batería', 'description' => 'Repetido'],
                ['item_name' => 'Fusibles principales', 'description' => 'Caja sin sulfatación'],
            ],
        ], $this->headers());

        $second->assertStatus(201);
        $this->assertSame(1, $second->json('data.created_count'));
        $this->assertSame(1, $second->json('data.skipped_count'));
    }

    public function test_descarga_plantilla_excel_devuelve_xlsx_valido(): void
    {
        $response = $this->get('/api/v1/catalogs/inspection-items/template', $this->headers());
        $response->assertOk();
        $contentType = (string) $response->headers->get('content-type');
        $this->assertStringContainsString('spreadsheetml', $contentType);
    }

    public function test_importacion_masiva_excel_crea_categorias_nuevas_e_items_y_omite_duplicados(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['CATEGORIA', 'NOMBRE_ITEM', 'DESCRIPCION'],
            ['DOCUMENTOS', 'Revisión Preventiva', 'Control bimestral'],
            ['DOCUMENTOS', 'Revisión Preventiva', 'Duplicado en archivo'],
            ['CARROCERIA ESPECIAL', 'Compuerta trasera', 'Cierre hermético'],
        ]);

        $tmpPath = tempnam(sys_get_temp_dir(), 'insp_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        $uploadedFile = new UploadedFile(
            $tmpPath,
            'carga_items.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        try {
            $response = $this->post('/api/v1/catalogs/inspection-items/import', [
                'file' => $uploadedFile,
            ], $this->headers());

            $response->assertOk();
            $this->assertSame(2, $response->json('data.created_count'));
            $this->assertSame(1, $response->json('data.skipped_count'));
            $this->assertSame(1, $response->json('data.categories_created'));

            $this->assertDatabaseHas('inspection_categories', ['code' => 'CARROCERIA_ESPECIAL']);
            $this->assertDatabaseHas('inspection_items', [
                'category' => 'CARROCERIA_ESPECIAL',
                'item_name' => 'Compuerta trasera',
            ]);
            $this->assertDatabaseHas('inspection_items', [
                'category' => 'DOCUMENTOS',
                'item_name' => 'Revisión Preventiva',
            ]);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function test_no_permite_eliminar_categoria_con_items_asociados_pero_si_vacia(): void
    {
        $emptyCat = $this->postJson('/api/v1/catalogs/inspection-items/categories', [
            'name' => 'Categoría Vacía',
        ], $this->headers())->assertStatus(201);

        $emptyUuid = $emptyCat->json('data.uuid');
        $this->deleteJson("/api/v1/catalogs/inspection-items/categories/{$emptyUuid}", [], $this->headers())
            ->assertOk();

        $busyCat = $this->postJson('/api/v1/catalogs/inspection-items/categories', [
            'name' => 'Categoría Ocupada',
        ], $this->headers())->assertStatus(201);

        $busyUuid = $busyCat->json('data.uuid');

        $this->postJson('/api/v1/catalogs/inspection-items/bulk', [
            'category' => 'CATEGORIA_OCUPADA',
            'items' => [['item_name' => 'Ítem bloqueador']],
        ], $this->headers())->assertStatus(201);

        $this->deleteJson("/api/v1/catalogs/inspection-items/categories/{$busyUuid}", [], $this->headers())
            ->assertStatus(400);
    }
}
