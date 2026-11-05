<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresa de origen del cambio de empresa (para liberar su cupo, Decreto
     * 348/2015) y referencia del paz y salvo de las desvinculaciones.
     */
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->uuid('origin_company_uuid')->nullable()->after('company_uuid');
            $table->string('paz_y_salvo', 100)->nullable()->after('resolution_number');

            $table->index('origin_company_uuid', 'idx_procedures_origin_company');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropIndex('idx_procedures_origin_company');
            $table->dropColumn(['origin_company_uuid', 'paz_y_salvo']);
        });
    }
};
