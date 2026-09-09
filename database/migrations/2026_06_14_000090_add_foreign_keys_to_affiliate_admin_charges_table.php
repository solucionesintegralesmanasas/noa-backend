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
        Schema::table('affiliate_admin_charges', function (Blueprint $table) {
            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_admin_charges', function (Blueprint $table) {
            $table->dropForeign(['vehicle_uuid']);
            $table->dropForeign(['company_uuid']);
        });
    }
};
