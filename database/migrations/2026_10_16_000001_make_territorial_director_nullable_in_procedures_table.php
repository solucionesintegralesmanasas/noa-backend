<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La Dirección territorial ya no se usa en la radicación de tarjeta de operación:
 * el formulario dejó de pedirla, así que el expediente debe poder crearse sin ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->uuid('territorial_director_uuid')->nullable()->comment('Dirección territorial (en desuso en radicación)')->change();
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->uuid('territorial_director_uuid')->nullable(false)->change();
        });
    }
};
