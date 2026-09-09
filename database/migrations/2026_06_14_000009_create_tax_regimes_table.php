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
        Schema::create('tax_regimes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID UNIVERSAL ÚNICO DEL RÉGIMEN FISCAL');
            $table->string('code', 10)->unique()->comment('CÓDIGO OFICIAL DIAN DEL RÉGIMEN FISCAL');
            $table->string('name', 100)->comment('NOMBRE DEL RÉGIMEN FISCAL');
            $table->string('description', 255)->comment('DESCRIPCIÓN DETALLADA DEL RÉGIMEN FISCAL');
            $table->boolean('is_active')->default(1)->comment('INDICA SI EL RÉGIMEN FISCAL SE ENCUENTRA VIGENTE');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `tax_regimes` COMMENT = 'Catálogo de regímenes tributarios según normativa DIAN'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_regimes');
    }
};
