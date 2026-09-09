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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID UNIVERSAL ÚNICO DEL MUNICIPIO');
            $table->uuid('department_uuid')->comment('UUID DEL DEPARTAMENTO AL QUE PERTENECE');
            $table->string('dane_code', 8)->comment('CÓDIGO OFICIAL DANE DEL MUNICIPIO');
            $table->string('name', 100)->comment('NOMBRE OFICIAL DEL MUNICIPIO');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `cities` COMMENT = 'Catálogo de ciudades y municipios colombianos'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
