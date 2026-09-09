<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID UNIVERSAL ÚNICO DE LA RESPONSABILIDAD TRIBUTARIA');
            $table->string('code', 20)->unique()->comment('CÓDIGO DIAN DE LA RESPONSABILIDAD TRIBUTARIA');
            $table->string('name', 255)->comment('NOMBRE OFICIAL DE LA RESPONSABILIDAD');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `tax_responsibilities` COMMENT = 'Catálogo de responsabilidades y obligaciones tributarias'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_responsibilities');
    }
};
