<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->json('meta')->nullable()->comment('Contexto de conversación: última entidad, término, tipo de respuesta')
                ->after('status');
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `chat_sessions` COMMENT = 'Sesiones de chat por usuario/empresa con memoria contextual'");
        }
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
