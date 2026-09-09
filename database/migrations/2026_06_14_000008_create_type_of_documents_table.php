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
        Schema::create('type_of_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID UNIVERSAL ÚNICO DEL TIPO DE DOCUMENTO');
            $table->string('name', 255)->comment('NOMBRE DESCRIPTIVO DEL TIPO DE DOCUMENTO (EJ: CC, NIT, CE, PASAPORTE)');
            $table->string('prefix', 255)->comment('PREFIJO ESTÁNDAR ASOCIADO AL TIPO DE DOCUMENTO');
            $table->boolean('status')->default(1)->comment('ESTADO DEL TIPO DE DOCUMENTO (1=ACTIVO, 0=INACTIVO)');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `type_of_documents` COMMENT = 'Catálogo de tipos de identificación legal y tributaria'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_of_documents');
    }
};
