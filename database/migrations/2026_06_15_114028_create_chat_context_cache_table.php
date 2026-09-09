<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_context_cache', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid')->comment('UUID del usuario');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->json('context')->comment('Preferencias o datos persistentes');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_uuid', 'company_uuid'], 'uq_user_company');

            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_context_cache');
    }
};
