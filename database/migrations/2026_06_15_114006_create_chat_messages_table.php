<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Identificador único público del mensaje');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->unsignedBigInteger('session_id')->comment('ID interno de la sesión');
            $table->enum('role', ['USUARIO', 'ASISTENTE', 'SISTEMA'])->comment('Rol del emisor');
            $table->longText('content')->comment('Contenido textual del mensaje');
            $table->unsignedInteger('tokens_used')->nullable()->comment('Cantidad de tokens consumidos');
            $table->float('response_time')->nullable()->comment('Tiempo de respuesta en segundos');
            $table->tinyInteger('feedback')->nullable()->comment('Calificación: 1=útil, -1=no útil');
            $table->json('metadata')->nullable()->comment('Datos adicionales: entidades, resultados ERP, errores');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['session_id', 'created_at'], 'idx_session_created');

            $table->foreign('session_id')->references('id')->on('chat_sessions')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
