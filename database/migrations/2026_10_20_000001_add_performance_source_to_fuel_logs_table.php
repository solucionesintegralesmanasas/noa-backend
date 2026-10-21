<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origen del rendimiento (km/gal) aplicado a un registro de combustible:
 * manual, registro (km/gal de la propia fila), historico o sin_dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->string('performance_source', 20)->nullable()->after('average_performance')
                ->comment('Origen del rendimiento: manual, registro, historico, sin_dato');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('performance_source');
        });
    }
};
