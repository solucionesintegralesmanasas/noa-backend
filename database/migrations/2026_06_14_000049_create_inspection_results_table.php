<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('inspection_uuid');
            $table->uuid('item_uuid');
            $table->boolean('is_selected')->default(1);
            $table->enum('status', ['APROBADO', 'NO_APROBADO', 'NO_APLICA'])->default('APROBADO');
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inspection_results');
    }
};
