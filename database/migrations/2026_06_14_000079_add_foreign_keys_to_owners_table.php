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
        Schema::table('owners', function (Blueprint $table) {
            $table->foreign('third_party_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->foreign('document_type_uuid')
                ->references('uuid')
                ->on('type_of_documents')
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
        Schema::table('owners', function (Blueprint $table) {
            $table->dropForeign(['third_party_uuid']);
            $table->dropForeign(['vehicle_uuid']);
            $table->dropForeign(['document_type_uuid']);
        });
    }
};
