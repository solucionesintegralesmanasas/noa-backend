<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $campos = ['first_name', 'last_name', 'company_name', 'trade_name'];

    public function up(): void
    {
        foreach ($this->campos as $campo) {
            DB::table('third_parties')
                ->whereNotNull($campo)
                ->orderBy('id')
                ->each(function ($fila) use ($campo) {
                    $nuevo = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $fila->$campo) ?? $fila->$campo), 'UTF-8');
                    if ($nuevo !== $fila->$campo) {
                        DB::table('third_parties')->where('id', $fila->id)->update([$campo => $nuevo]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Irreversible: no se conserva el texto original.
    }
};
