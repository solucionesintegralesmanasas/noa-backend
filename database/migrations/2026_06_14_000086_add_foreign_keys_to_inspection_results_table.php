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
        Schema::table('inspection_results', function (Blueprint $table) {
            $table->foreign('inspection_uuid')
                ->references('uuid')
                ->on('vehicle_inspections')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('item_uuid')
                ->references('uuid')
                ->on('inspection_items')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inspection_results', function (Blueprint $table) {
            $table->dropForeign(['inspection_uuid']);
            $table->dropForeign(['item_uuid']);
        });
    }
};
