<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina los artefactos de ApiDian tras migrar a Factus como único proveedor.
     *
     * - Tabla `electronic_documents`: documentos genéricos de ApiDian (soporte,
     *   equivalentes, nómina, eventos RADIAN, lote). Fuera del alcance de Factus.
     * - Columna `test_set_id` de `billing_providers`: set de pruebas de ApiDian.
     *   Factus distingue sandbox/producción por la URL (`server_url`).
     */
    public function up(): void
    {
        Schema::dropIfExists('electronic_documents');

        Schema::table('billing_providers', function (Blueprint $table) {
            $table->dropColumn('test_set_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->string('test_set_id', 100)->nullable()->after('server_url');
        });

        // La tabla no se reconstruye en down() (no se puede recuperar el esquema
        // completo de electronic_documents sin duplicar su migración original).
    }
};
