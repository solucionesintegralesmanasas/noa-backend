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
        Schema::create('territorial_directors', function (Blueprint $table) {
            $table->id()->comment('Identificador único del director territorial');
            $table->uuid('uuid')->unique()->comment('UUID único universal del director territorial');
            $table->string('name', 255)->comment('Nombre completo del director territorial');
            $table->string('territorial_director', 255)->comment('Cargo o denominación oficial del director territorial');
            $table->boolean('is_active')->default(true)->comment('Estado de habilitación del director territorial');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territorial_directors');
    }
};
