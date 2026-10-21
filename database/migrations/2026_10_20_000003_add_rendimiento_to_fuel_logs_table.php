<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el "Rendimiento" del registro: Total Tanqueo ÷ Rendimiento (km/gal).
 * El "Costo del kilometraje" usa la columna existente `cost_per_km`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('rendimiento', 12, 2)->nullable()->after('average_performance')
                ->comment('Total tanqueo ÷ rendimiento (km/gal)');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('rendimiento');
        });
    }
};
