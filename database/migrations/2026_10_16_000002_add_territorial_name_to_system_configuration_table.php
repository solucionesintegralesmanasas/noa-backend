<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre de la territorial del Ministerio de Transporte con la que opera la empresa
 * (texto libre, ej. "Cesar", "Atlántico", "Cundinamarca"). Complementa a
 * `default_territorial_director_name` (nombre de la directora o director).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('system_configuration', 'territorial_name')) {
            Schema::table('system_configuration', function (Blueprint $table) {
                $table->string('territorial_name', 150)->nullable()->after('default_territorial_director_name')
                    ->comment('Nombre de la territorial del Ministerio de Transporte');
            });
        }
    }

    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn('territorial_name');
        });
    }
};
