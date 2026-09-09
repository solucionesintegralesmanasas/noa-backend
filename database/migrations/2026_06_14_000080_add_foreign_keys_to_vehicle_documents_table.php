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
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
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
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropForeign(['vehicle_uuid']);
            $table->dropForeign(['company_uuid']);
        });
    }
};
