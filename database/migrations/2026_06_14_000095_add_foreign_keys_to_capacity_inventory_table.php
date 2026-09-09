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
        Schema::table('capacity_inventory', function (Blueprint $table) {
            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('procedure_uuid')
                ->references('uuid')
                ->on('procedures')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capacity_inventory', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['procedure_uuid']);
        });
    }
};
