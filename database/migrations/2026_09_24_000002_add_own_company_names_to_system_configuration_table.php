<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la lista de nombres que identifican a la empresa propia.
     * Se compara contra el campo affiliated_company (texto libre) de las
     * tarjetas de operación para clasificar las alertas prioritarias.
     */
    public function up(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->json('own_company_names')->nullable()->after('notification_email')
                ->comment('Nombres que identifican a la empresa propia en tarjetas de operación');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn('own_company_names');
        });
    }
};
