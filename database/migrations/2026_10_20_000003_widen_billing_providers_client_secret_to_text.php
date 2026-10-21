<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía client_secret a text: el cast `encrypted` guarda el token
     * cifrado con AES (iv + valor + mac en base64), que con tokens reales
     * del proveedor supera los 255 caracteres de varchar (falla con 1406).
     */
    public function up(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->text('client_secret')->nullable()->comment('Token del proveedor, cifrado en reposo (cast encrypted)')->change();
        });
    }

    public function down(): void
    {
        Schema::table('billing_providers', function (Blueprint $table) {
            $table->string('client_secret', 255)->nullable()->comment('Token del proveedor, cifrado en reposo (cast encrypted)')->change();
        });
    }
};
