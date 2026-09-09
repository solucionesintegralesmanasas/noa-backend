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
        Schema::table('social_security_contributions', function (Blueprint $table) {
            $table->foreign('third_party_uuid')
                ->references('uuid')
                ->on('third_parties')
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
        Schema::table('social_security_contributions', function (Blueprint $table) {
            $table->dropForeign(['third_party_uuid']);
        });
    }
};
