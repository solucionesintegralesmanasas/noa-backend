<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens OAuth2 del driver Factus (proveedor tecnológico).
     *
     * Factus autentica con OAuth2 "password": devuelve un access_token
     * (de vida corta, ~1 h) y un refresh_token. Ambos se guardan cifrados
     * en reposo (cast `encrypted`). `access_token_expires_at` permite
     * refrescar de forma proactiva antes de enviar, sin esperar un 401.
     *
     * En ApiDian el access_token vivía en `client_secret`; Factus necesita
     * `client_secret` para el secreto de la aplicación, por lo que los
     * tokens viajan en columnas propias.
     */
    public function up(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->text('access_token')->nullable()->after('client_password')
                ->comment('Access token OAuth2 de Factus, cifrado en reposo (cast encrypted)');
            $table->text('refresh_token')->nullable()->after('access_token')
                ->comment('Refresh token OAuth2 de Factus, cifrado en reposo (cast encrypted)');
            $table->dateTime('access_token_expires_at')->nullable()->after('refresh_token')
                ->comment('Vencimiento del access token de Factus');
        });
    }

    public function down(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'refresh_token', 'access_token_expires_at']);
        });
    }
};
