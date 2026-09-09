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
        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->foreign('maintenance_uuid')
                ->references('uuid')
                ->on('maintenance')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('supplier_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onDelete('set null')
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
        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->dropForeign(['maintenance_uuid']);
            $table->dropForeign(['supplier_uuid']);
        });
    }
};
