<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Identificador único público de la sesión');
            $table->uuid('user_uuid')->comment('UUID del usuario');
            $table->uuid('company_uuid')->nullable()->comment('UUID de la empresa');
            $table->string('title', 255)->nullable()->comment('Título generado o asignado a la conversación');
            $table->enum('module', ['transporte', 'facturacion', 'inventario', 'general'])->nullable()->comment('Módulo ERP asociado');
            $table->enum('status', ['ACTIVA', 'CERRADA'])->default('ACTIVA')->comment('Estado actual de la sesión');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['user_uuid', 'module'], 'idx_user_module');

            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
