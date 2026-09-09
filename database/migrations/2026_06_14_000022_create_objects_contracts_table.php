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
        Schema::create('objects_contracts', function (Blueprint $table) {
            $table->id()->comment('Identificador único del objeto de contrato');
            $table->uuid('uuid')->unique()->comment('UUID único universal del objeto de contrato');
            $table->string('name')->comment('Nombre del objeto de contrato');
            $table->text('description')->comment('Descripción del objeto de contrato');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objects_contracts');
    }
};
