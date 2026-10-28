<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Añade índice único compuesto (company_uuid, procedure_code) en procedures.
 *
 * Previo a la creación del índice, depura cualquier duplicado histórico preexistente
 * sufijando el código duplicado con `-DUP-<id>` para preservar la integridad y el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Depuración de duplicados históricos si existieran
        $duplicados = DB::table('procedures')
            ->select('company_uuid', 'procedure_code', DB::raw('count(*) as total'))
            ->whereNotNull('company_uuid')
            ->whereNotNull('procedure_code')
            ->groupBy('company_uuid', 'procedure_code')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicados as $dup) {
            $filas = DB::table('procedures')
                ->where('company_uuid', $dup->company_uuid)
                ->where('procedure_code', $dup->procedure_code)
                ->orderByRaw('CASE WHEN parent_procedure_uuid IS NULL OR parent_procedure_uuid = "" THEN 0 ELSE 1 END ASC')
                ->orderBy('id', 'asc')
                ->get();

            // Conservamos la primera fila intacta y renombramos las duplicadas posteriores
            $primera = true;
            foreach ($filas as $fila) {
                if ($primera) {
                    $primera = false;
                    continue;
                }

                $nuevoCodigo = $fila->procedure_code . '-DUP-' . $fila->id;

                DB::table('procedures')
                    ->where('id', $fila->id)
                    ->update(['procedure_code' => $nuevoCodigo]);

                // Si la fila duplicada tenía hijos vinculados, actualizar su prefijo de código
                DB::table('procedures')
                    ->where('parent_procedure_uuid', $fila->uuid)
                    ->where('procedure_code', 'like', $dup->procedure_code . '-%')
                    ->update([
                        'procedure_code' => DB::raw("REPLACE(procedure_code, '" . $dup->procedure_code . "-', '" . $nuevoCodigo . "-')"),
                    ]);
            }
        }

        // 2. Crear índice único compuesto en procedures
        Schema::table('procedures', function (Blueprint $table) {
            $table->unique(['company_uuid', 'procedure_code'], 'procedures_company_procedure_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropUnique('procedures_company_procedure_code_unique');
        });
    }
};
