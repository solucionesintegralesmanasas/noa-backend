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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID UNIVERSAL ÚNICO DEL DEPARTAMENTO');
            $table->string('dane_code', 5)->unique()->comment('CÓDIGO OFICIAL DANE DEL DEPARTAMENTO');
            $table->string('name', 100)->comment('NOMBRE OFICIAL DEL DEPARTAMENTO');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `departments` COMMENT = 'Catálogo de departamentos de Colombia'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
