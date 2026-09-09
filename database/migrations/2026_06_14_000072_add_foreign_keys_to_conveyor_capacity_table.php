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
        Schema::table('conveyor_capacity', function (Blueprint $table) {
            $table->foreign('enabling_resolution_uuid')->references('uuid')->on('enabling_resolutions')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conveyor_capacity', function (Blueprint $table) {
            $table->dropForeign(['enabling_resolution_uuid']);
        });
    }
};
