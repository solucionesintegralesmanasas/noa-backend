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
        Schema::table('business_collaboration_agreements', function (Blueprint $table) {
            $table->string('resolution_number', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_collaboration_agreements', function (Blueprint $table) {
            $table->string('resolution_number', 50)->nullable(false)->change();
        });
    }
};
