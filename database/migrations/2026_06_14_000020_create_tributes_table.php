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
        Schema::create('tributes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 ÚNICO DEL TRIBUTO')->unique();
            $table->string('dian_code', 10)->comment('CÓDIGO DIAN DEL TRIBUTO (01:IVA, 04:INC, ZA:NO APLICA, ETC.)')->unique();
            $table->string('name', 100)->comment('NOMBRE OFICIAL DEL TRIBUTO SEGÚN DIAN');
            $table->string('description', 255)->nullable()->comment('DESCRIPCIÓN O ACLARACIÓN DEL TRIBUTO');
            $table->boolean('is_active')->default(1)->comment('ESTADO DE HABILITACIÓN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tributes');
    }
};
