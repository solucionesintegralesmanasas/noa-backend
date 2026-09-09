<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contextual_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID ÚNICO DE LA PREGUNTA CONTEXTUAL');
            $table->string('module', 100)->comment('MÓDULO: fleet, third_parties, contract_extract, administration, general');
            $table->string('title', 255)->comment('TÍTULO DESCRIPTIVO');
            $table->text('question_text')->comment('TEXTO DE PREGUNTA VISIBLE EN UI');
            $table->json('patterns')->comment('PATRONES DE BÚSQUEDA: ["cuantos vehiculos", "total vehiculos"]');
            $table->string('response_type', 50)->comment('TIPO: count, list, expiring, text');
            $table->text('response_template')->comment('TEMPLATE CON {count} {results} {entity}');
            $table->string('entity_type', 100)->nullable()->comment('ENTIDAD: vehicles, third_parties, fuecs...');
            $table->json('filters')->nullable()->comment('FILTROS: {"is_active": true}');
            $table->integer('expiring_days')->nullable()->comment('DÍAS PARA VENCIMIENTO');
            $table->integer('sort_order')->default(0)->comment('ORDEN DE PRIORIDAD');
            $table->boolean('is_active')->default(true)->comment('1=ACTIVO, 0=INACTIVO');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `contextual_questions` COMMENT = 'Preguntas contextuales para el asistente virtual'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contextual_questions');
    }
};
