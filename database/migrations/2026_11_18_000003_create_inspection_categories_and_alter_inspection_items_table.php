<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Categorías predefinidas del catálogo original.
     *
     * @var array<string, string>
     */
    private const BASE_CATEGORIES = [
        'DOCUMENTOS' => 'Documentos',
        'DOTACION' => 'Dotación',
        'VIDRIOS_ESPEJOS' => 'Vidrios y Espejos',
        'OTROS' => 'Otros',
        'EMERGENCIAS' => 'Emergencias',
        'EXTINTOR' => 'Extintor',
        'HERRAMIENTAS' => 'Herramientas',
        'LUCES' => 'Luces',
        'FLUIDOS' => 'Fluidos',
        'NEUMATICOS' => 'Neumáticos',
        'PRESION' => 'Presión',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('inspection_categories')) {
            Schema::create('inspection_categories', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->comment('UUID ÚNICO UNIVERSAL DE LA CATEGORÍA DE INSPECCIÓN');
                $table->string('code', 100)->unique()->comment('CÓDIGO NORMALIZADO DE LA CATEGORÍA');
                $table->string('name', 100)->comment('NOMBRE LEGIBLE DE LA CATEGORÍA');
                $table->text('description')->nullable()->comment('DESCRIPCIÓN OPCIONAL DE LA CATEGORÍA');
                $table->timestamps();
            });
        }

        // Pasar category de ENUM a VARCHAR(100) para permitir categorías dinámicas creadas por los usuarios.
        DB::statement("ALTER TABLE inspection_items MODIFY COLUMN category VARCHAR(100) NOT NULL COMMENT 'CATEGORÍA DEL ITEM DE INSPECCIÓN'");

        if (! $this->hasIndex('inspection_items', 'idx_inspection_items_category')) {
            Schema::table('inspection_items', function (Blueprint $table) {
                $table->index('category', 'idx_inspection_items_category');
            });
        }

        $now = now();
        $rows = [];

        foreach (self::BASE_CATEGORIES as $code => $name) {
            $rows[$code] = [
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Preservar cualquier categoría ya almacenada en inspection_items fuera de las 11 iniciales.
        $existingCategories = DB::table('inspection_items')
            ->select('category')
            ->distinct()
            ->pluck('category');

        foreach ($existingCategories as $rawCode) {
            $code = strtoupper(trim((string) $rawCode));
            if ($code !== '' && ! isset($rows[$code])) {
                $rows[$code] = [
                    'uuid' => (string) Str::uuid(),
                    'code' => $code,
                    'name' => ucwords(strtolower(str_replace('_', ' ', $code))),
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('inspection_categories')->insertOrIgnore(array_values($rows));
    }

    public function down(): void
    {
        if ($this->hasIndex('inspection_items', 'idx_inspection_items_category')) {
            Schema::table('inspection_items', function (Blueprint $table) {
                $table->dropIndex('idx_inspection_items_category');
            });
        }

        Schema::dropIfExists('inspection_categories');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
