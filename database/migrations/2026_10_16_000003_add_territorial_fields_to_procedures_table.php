<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dirección territorial del expediente de radicación como texto: el nombre de la
 * directora del Ministerio y la territorial (se capturan en el formulario de nueva
 * radicación, prerrellenados desde la configuración de la empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            if (! Schema::hasColumn('procedures', 'territorial_director_name')) {
                $table->string('territorial_director_name', 200)->nullable()->after('territorial_director_uuid')
                    ->comment('Nombre de la directora o director del Ministerio de Transporte');
            }
            if (! Schema::hasColumn('procedures', 'territorial_name')) {
                $table->string('territorial_name', 150)->nullable()->after('territorial_director_name')
                    ->comment('Territorial del Ministerio (ej. Cesar, Atlántico)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn(['territorial_director_name', 'territorial_name']);
        });
    }
};
