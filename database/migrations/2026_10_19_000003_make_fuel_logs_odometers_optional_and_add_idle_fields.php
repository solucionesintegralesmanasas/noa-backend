<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El kilometraje pasa a ser opcional (solo valor galón + tanqueo son obligatorios).
     * Se añaden los parámetros del consumo estimado por ralentí según cilindraje.
     */
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->unsignedInteger('initial_odometer')->nullable()->change();
            $table->unsignedInteger('final_odometer')->nullable()->change();
            $table->unsignedInteger('recorrido')->nullable()->change();

            $table->decimal('idle_hours', 5, 2)->default(7)->after('engine_displacement');
            $table->decimal('idle_factor', 6, 4)->nullable()->after('idle_hours');
            $table->decimal('displacement_liters', 6, 3)->nullable()->after('idle_factor');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn(['idle_hours', 'idle_factor', 'displacement_liters']);
        });
    }
};
