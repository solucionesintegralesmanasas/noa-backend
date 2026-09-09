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
        Schema::table('contractors', function (Blueprint $table) {
            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('document_type_uuid')
                ->references('uuid')
                ->on('type_of_documents')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['document_type_uuid']);
            $table->dropForeign(['vehicle_uuid']);
        });
    }
};
