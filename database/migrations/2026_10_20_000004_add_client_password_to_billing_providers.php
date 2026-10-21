<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credenciales de login del portal ApiDian (colección, paso 00):
     * POST /api/auth/login con email + password devuelve un access_token.
     *
     * El token vive en client_secret (cifrado); la contraseña se guarda
     * cifrada aparte para poder renovarlo cuando expire (JWT de 1 h).
     */
    public function up(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->text('client_password')->nullable()->after('client_secret')
                ->comment('Contraseña del portal ApiDian, cifrada en reposo (cast encrypted)');
        });
    }

    public function down(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->dropColumn('client_password');
        });
    }
};
