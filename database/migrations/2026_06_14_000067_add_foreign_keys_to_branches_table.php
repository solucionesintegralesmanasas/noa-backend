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
        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade');
            $table->foreign('municipality_uuid')->references('uuid')->on('cities')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['municipality_uuid']);
        });
    }
};
