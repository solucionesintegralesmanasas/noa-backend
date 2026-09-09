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
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('municipality_uuid')->references('uuid')->on('cities')->onDelete('restrict')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['municipality_uuid']);
        });
    }
};
