<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el alcance estimado (km) de los galones ingresados en cada registro,
 * calculado como galones × rendimiento aplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('estimated_range_km', 12, 2)->nullable()->after('km_per_gallon')
                ->comment('Alcance estimado de los galones ingresados (km)');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('estimated_range_km');
        });
    }
};
