<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID ÚNICO UNIVERSAL DEL ITEM DE INSPECCIÓN');
            $table->enum('category', ['DOCUMENTOS', 'DOTACION', 'VIDRIOS_ESPEJOS', 'OTROS', 'EMERGENCIAS', 'EXTINTOR', 'HERRAMIENTAS', 'LUCES', 'FLUIDOS', 'NEUMATICOS', 'PRESION'])->comment('CATEGORÍA DEL ITEM DE INSPECCIÓN');
            $table->string('item_name', 150)->comment('NOMBRE DEL ITEM DE INSPECCIÓN');
            $table->text('description')->nullable()->comment('DESCRIPCIÓN DEL ITEM DE INSPECCIÓN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
