<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            // Logo del Ministerio de Transporte (base64)
            $table->longText('ministry_logo')->nullable()->after('fuec_pdf_show_route_details')
                ->comment('Logo del Ministerio de Transporte en base64 para el PDF del FUEC');
            
            // Logo de la Superintendencia (base64)
            $table->longText('super_logo')->nullable()->after('ministry_logo')
                ->comment('Logo de la Superintendencia en base64 para el PDF del FUEC');
            
            // Hoja membretada (base64)
            $table->longText('letterhead')->nullable()->after('super_logo')
                ->comment('Imagen de hoja membretada en base64 para el PDF del FUEC');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn(['ministry_logo', 'super_logo', 'letterhead']);
        });
    }
};
