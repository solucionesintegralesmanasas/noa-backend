<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runt_txt_batches', function (Blueprint $table) {
            $table->unsignedInteger('consecutive')->nullable()->after('sequence');
            $table->index(['company_uuid', 'consecutive'], 'idx_rtb_company_consecutive');
        });

        // Numerar los lotes existentes por empresa en orden de creación.
        $lotes = DB::table('runt_txt_batches')
            ->select('id', 'company_uuid')
            ->orderBy('id')
            ->get()
            ->groupBy('company_uuid');

        foreach ($lotes as $filas) {
            $numero = 0;
            foreach ($filas as $fila) {
                DB::table('runt_txt_batches')
                    ->where('id', $fila->id)
                    ->update(['consecutive' => ++$numero]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('runt_txt_batches', function (Blueprint $table) {
            $table->dropIndex('idx_rtb_company_consecutive');
            $table->dropColumn('consecutive');
        });
    }
};
