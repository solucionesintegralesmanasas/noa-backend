<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Exceptions\GeneralException;
use App\Models\InspectionCategory;
use App\Models\InspectionItem;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Servicio de negocio para la gestión integral de Ítems y Categorías de Inspección.
 */
class InspectionItemService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['category', 'item_name', 'description'];

    public function __construct()
    {
        parent::__construct();
        $this->sortableFields = [
            'category' => 'inspection_items.category',
            'item_name' => 'inspection_items.item_name',
            'description' => 'inspection_items.description',
        ];
    }

    protected function getModelInstance(): Model
    {
        return new InspectionItem;
    }

    /**
     * Listado paginado de ítems de inspección con búsqueda, orden y filtro opcional por categoría.
     */
    public function getAllInspectionItemsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $sortBy = null,
        string $sortOrder = 'asc',
        ?string $category = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($category !== null && trim($category) !== '') {
            $normalized = InspectionCategory::normalizeCode($category);
            if ($normalized !== '') {
                $query->where('category', $normalized);
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        $this->applySorting($query, $sortBy, $sortOrder);
        if ($sortBy === null || $sortBy === '' || ! array_key_exists($sortBy, $this->sortableFields)) {
            $query->orderBy('category')->orderBy('item_name');
        }

        return $query->paginate($perPage, ['uuid', 'category', 'item_name', 'description'], 'page', $page);
    }

    /**
     * Catálogo completo de ítems para checklist.
     */
    public function getAllInspectionItems(): Collection
    {
        return $this->all(columns: ['uuid', 'category', 'item_name', 'description']);
    }

    /**
     * Busca un ítem de inspección por UUID.
     */
    public function getInspectionItemByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Crea un ítem individual asegurando que la categoría exista en el catálogo.
     */
    public function createInspectionItem(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $categoryCode = $this->ensureCategoryExists((string) $data['category']);

            return InspectionItem::create([
                'category' => $categoryCode,
                'item_name' => trim((string) $data['item_name']),
                'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                    ? trim((string) $data['description'])
                    : null,
            ]);
        });
    }

    /**
     * Actualiza un ítem existente asegurando que la categoría exista si fue modificada.
     */
    public function updateInspectionItem(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var InspectionItem $record */
            $record = InspectionItem::query()->where('uuid', $uuid)->firstOrFail();

            $categoryCode = ! empty($data['category'])
                ? $this->ensureCategoryExists((string) $data['category'])
                : $record->category;

            $itemName = array_key_exists('item_name', $data) && trim((string) $data['item_name']) !== ''
                ? trim((string) $data['item_name'])
                : $record->item_name;

            $description = array_key_exists('description', $data)
                ? (trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null)
                : $record->description;

            $record->update([
                'category' => $categoryCode,
                'item_name' => $itemName,
                'description' => $description,
            ]);

            return $record;
        });
    }

    /**
     * Elimina un ítem por UUID.
     */
    public function deleteInspectionItem(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('InspectionItemService@deleteInspectionItem: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista todas las categorías registradas con el conteo SQL de ítems asociados.
     */
    public function getCategories(): SupportCollection
    {
        $counts = DB::table('inspection_items')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->pluck('items_count', 'category');

        return InspectionCategory::query()
            ->select(['uuid', 'code', 'name', 'description'])
            ->orderBy('name')
            ->get()
            ->map(function (InspectionCategory $cat) use ($counts) {
                return [
                    'uuid' => $cat->uuid,
                    'code' => $cat->code,
                    'name' => $cat->name,
                    'description' => $cat->description,
                    'items_count' => (int) ($counts[$cat->code] ?? 0),
                ];
            });
    }

    /**
     * Crea una nueva categoría de inspección en el catálogo.
     */
    public function createCategory(array $data): InspectionCategory
    {
        $name = trim((string) $data['name']);
        $rawCode = ! empty($data['code']) ? (string) $data['code'] : $name;
        $code = InspectionCategory::normalizeCode($rawCode);

        if ($code === '') {
            throw GeneralException::badRequest('El nombre de la categoría no genera un código válido.');
        }

        if (InspectionCategory::query()->where('code', $code)->exists()) {
            throw GeneralException::duplicateEntry("Ya existe una categoría con el código {$code}.");
        }

        return $this->transaction(fn () => InspectionCategory::create([
            'code' => $code,
            'name' => $name,
            'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null,
        ]));
    }

    /**
     * Actualiza una categoría y propaga el cambio de código hacia inspection_items si aplica.
     */
    public function updateCategory(string $uuid, array $data): InspectionCategory
    {
        return $this->transaction(function () use ($uuid, $data) {
            $category = InspectionCategory::query()->where('uuid', $uuid)->first();
            if (! $category) {
                throw GeneralException::notFound('La categoría solicitada no existe.');
            }

            $oldCode = $category->code;
            $name = trim((string) $data['name']);
            $newCode = ! empty($data['code'])
                ? InspectionCategory::normalizeCode((string) $data['code'])
                : $oldCode;

            if ($newCode === '') {
                throw GeneralException::badRequest('El código de categoría no es válido.');
            }

            if ($newCode !== $oldCode && InspectionCategory::query()->where('code', $newCode)->where('uuid', '!=', $uuid)->exists()) {
                throw GeneralException::duplicateEntry("Ya existe otra categoría con el código {$newCode}.");
            }

            $category->update([
                'code' => $newCode,
                'name' => $name,
                'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                    ? trim((string) $data['description'])
                    : null,
            ]);

            if ($newCode !== $oldCode) {
                DB::table('inspection_items')
                    ->where('category', $oldCode)
                    ->update(['category' => $newCode, 'updated_at' => now()]);
            }

            return $category->fresh();
        });
    }

    /**
     * Elimina una categoría solo si no tiene ítems de inspección asociados.
     */
    public function deleteCategory(string $uuid): void
    {
        $category = InspectionCategory::query()->where('uuid', $uuid)->first();
        if (! $category) {
            throw GeneralException::notFound('La categoría solicitada no existe.');
        }

        if (DB::table('inspection_items')->where('category', $category->code)->exists()) {
            throw GeneralException::badRequest('No se puede eliminar la categoría porque tiene ítems de inspección asociados.');
        }

        $category->delete();
    }

    /**
     * Crea en lote múltiples ítems para una misma categoría, omitiendo duplicados existentes.
     *
     * @param  array<int, array{item_name: string, description?: ?string}>  $items
     * @return array{category: string, created_count: int, skipped_count: int, items: array<int, array<string, mixed>>}
     */
    public function bulkCreateInspectionItems(string $categoryInput, array $items): array
    {
        return $this->transaction(function () use ($categoryInput, $items) {
            $categoryCode = $this->ensureCategoryExists($categoryInput);

            $existingMap = DB::table('inspection_items')
                ->where('category', $categoryCode)
                ->pluck('item_name')
                ->mapWithKeys(fn ($name) => [mb_strtolower(trim((string) $name), 'UTF-8') => true])
                ->all();

            $now = now();
            $rows = [];
            $skipped = 0;

            foreach ($items as $entry) {
                $itemName = trim((string) ($entry['item_name'] ?? ''));
                if ($itemName === '') {
                    continue;
                }

                $key = mb_strtolower($itemName, 'UTF-8');
                if (isset($existingMap[$key])) {
                    $skipped++;

                    continue;
                }

                $existingMap[$key] = true;
                $description = isset($entry['description']) && trim((string) $entry['description']) !== ''
                    ? trim((string) $entry['description'])
                    : null;

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'category' => $categoryCode,
                    'item_name' => mb_substr($itemName, 0, 150, 'UTF-8'),
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('inspection_items')->insert($chunk);
            }

            $createdItems = array_map(fn (array $row) => [
                'uuid' => $row['uuid'],
                'category' => $row['category'],
                'item_name' => $row['item_name'],
                'description' => $row['description'],
            ], $rows);

            return [
                'category' => $categoryCode,
                'created_count' => count($rows),
                'skipped_count' => $skipped,
                'items' => $createdItems,
            ];
        });
    }

    /**
     * Importa ítems de inspección desde un archivo Excel/CSV.
     * Si una categoría del Excel no existe aún, se crea automáticamente en el catálogo.
     *
     * @return array{created_count: int, skipped_count: int, categories_created: int, errors: array<int, string>}
     */
    public function importFromExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetRows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);

        if (empty($sheetRows)) {
            throw GeneralException::badRequest('El archivo Excel está vacío.');
        }

        // Detectar fila de encabezados en la primera fila para omitirla automáticamente.
        $startIndex = 0;
        if ($this->isHeaderRow($sheetRows[0])) {
            $startIndex = 1;
        }

        $parsedRows = [];
        $errors = [];
        $categoriesToEnsure = [];

        for ($i = $startIndex; $i < count($sheetRows); $i++) {
            $row = $sheetRows[$i];
            $rawCategory = trim((string) ($row[0] ?? ''));
            $rawName = trim((string) ($row[1] ?? ''));
            $rawDescription = trim((string) ($row[2] ?? ''));

            // Omitir filas totalmente en blanco.
            if ($rawCategory === '' && $rawName === '' && $rawDescription === '') {
                continue;
            }

            $excelRowNumber = $i + 1;

            if ($rawCategory === '' || $rawName === '') {
                $errors[] = "Fila {$excelRowNumber}: Las columnas CATEGORIA y NOMBRE_ITEM son obligatorias.";

                continue;
            }

            $categoryCode = InspectionCategory::normalizeCode($rawCategory);
            if ($categoryCode === '') {
                $errors[] = "Fila {$excelRowNumber}: La categoría \"{$rawCategory}\" no es válida.";

                continue;
            }

            if (mb_strlen($rawName, 'UTF-8') > 150) {
                $errors[] = "Fila {$excelRowNumber}: El nombre del ítem supera los 150 caracteres.";

                continue;
            }

            if (! isset($categoriesToEnsure[$categoryCode])) {
                $categoriesToEnsure[$categoryCode] = InspectionCategory::formatLabel($rawCategory);
            }

            $parsedRows[] = [
                'category' => $categoryCode,
                'item_name' => $rawName,
                'description' => $rawDescription !== '' ? $rawDescription : null,
            ];
        }

        if (empty($parsedRows) && empty($errors)) {
            throw GeneralException::badRequest('No se encontraron filas con datos para importar en el archivo.');
        }

        return $this->transaction(function () use ($parsedRows, $categoriesToEnsure, $errors) {
            $now = now();

            // 1. Crear en bloque las categorías nuevas encontradas en el archivo.
            $existingCategoryCodes = DB::table('inspection_categories')
                ->pluck('code')
                ->flip()
                ->all();

            $newCategories = [];
            foreach ($categoriesToEnsure as $code => $label) {
                if (! isset($existingCategoryCodes[$code])) {
                    $newCategories[] = [
                        'uuid' => (string) Str::uuid(),
                        'code' => $code,
                        'name' => $label !== '' ? $label : $code,
                        'description' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($newCategories)) {
                DB::table('inspection_categories')->insertOrIgnore($newCategories);
            }

            // 2. Cargar en 1 sola consulta los ítems existentes de las categorías involucradas.
            $touchedCodes = array_keys($categoriesToEnsure);
            $existingItemsMap = [];

            if (! empty($touchedCodes)) {
                $existingRecords = DB::table('inspection_items')
                    ->whereIn('category', $touchedCodes)
                    ->get(['category', 'item_name']);

                foreach ($existingRecords as $record) {
                    $key = $record->category.'||'.mb_strtolower(trim((string) $record->item_name), 'UTF-8');
                    $existingItemsMap[$key] = true;
                }
            }

            // 3. Deduplicar e insertar en bloque.
            $rowsToInsert = [];
            $skippedCount = 0;

            foreach ($parsedRows as $entry) {
                $key = $entry['category'].'||'.mb_strtolower($entry['item_name'], 'UTF-8');
                if (isset($existingItemsMap[$key])) {
                    $skippedCount++;

                    continue;
                }

                $existingItemsMap[$key] = true;
                $rowsToInsert[] = [
                    'uuid' => (string) Str::uuid(),
                    'category' => $entry['category'],
                    'item_name' => $entry['item_name'],
                    'description' => $entry['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rowsToInsert, 500) as $chunk) {
                DB::table('inspection_items')->insert($chunk);
            }

            return [
                'created_count' => count($rowsToInsert),
                'skipped_count' => $skippedCount,
                'categories_created' => count($newCategories),
                'errors' => $errors,
            ];
        });
    }

    /**
     * Garantiza que el código de categoría exista en inspection_categories y retorna su código normalizado.
     */
    private function ensureCategoryExists(string $categoryInput, ?string $preferredLabel = null): string
    {
        $code = InspectionCategory::normalizeCode($categoryInput);
        if ($code === '') {
            throw GeneralException::badRequest('La categoría proporcionada no es válida.');
        }

        $exists = DB::table('inspection_categories')->where('code', $code)->exists();
        if (! $exists) {
            $label = $preferredLabel ?: InspectionCategory::formatLabel($categoryInput);
            DB::table('inspection_categories')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $label !== '' ? $label : $code,
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $code;
    }

    /**
     * Determina si la primera fila del Excel corresponde a los encabezados esperados.
     *
     * @param  array<int, mixed>  $row
     */
    private function isHeaderRow(array $row): bool
    {
        $col0 = strtolower(Str::ascii(trim((string) ($row[0] ?? ''))));
        $col1 = strtolower(Str::ascii(trim((string) ($row[1] ?? ''))));

        return (str_contains($col0, 'categoria') || str_contains($col0, 'category'))
            && (str_contains($col1, 'nombre') || str_contains($col1, 'item'));
    }
}
