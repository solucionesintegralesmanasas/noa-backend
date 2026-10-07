<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presentación por documento PDF (membrete / logo de fondo / limpio).
     * JSON { "<clave pdf>": "membrete"|"fondo"|"limpio" }. Nulo = defecto (fondo).
     */
    public function up(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->json('pdf_branding')->nullable()->after('fuec_pdf_show_route_details')
                ->comment('Presentación por documento PDF: membrete, fondo o limpio. Nulo = logo de fondo.');
        });
    }

    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn('pdf_branding');
        });
    }
};
