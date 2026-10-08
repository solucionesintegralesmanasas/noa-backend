<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla `licenses` nació sin columna `id`: Eloquent asume `id` como clave y
 * sin ella `save()` intenta INSERT en vez de UPDATE (revocar, renovar o expirar
 * rompía con duplicado de `license_key`). Se añade la PK autoincremental.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->id()->first();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
