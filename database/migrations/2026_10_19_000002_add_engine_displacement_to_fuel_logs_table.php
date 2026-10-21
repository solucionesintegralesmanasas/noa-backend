<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot del cilindraje del vehículo al momento del registro de combustible.
     * Se consulta desde vehicles.engine_displacement al crear/actualizar.
     */
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->string('engine_displacement', 20)
                ->nullable()
                ->after('driver_uuid')
                ->comment('Cilindraje del motor (snapshot del vehículo)');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('engine_displacement');
        });
    }
};
