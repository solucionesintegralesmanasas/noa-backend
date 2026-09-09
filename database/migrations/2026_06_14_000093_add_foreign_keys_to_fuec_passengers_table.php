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
        Schema::table('fuec_passengers', function (Blueprint $table) {
            $table->foreign('fuec_uuid')
                ->references('uuid')
                ->on('fuec')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('type_of_document_uuid')
                ->references('uuid')
                ->on('type_of_documents')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuec_passengers', function (Blueprint $table) {
            $table->dropForeign(['fuec_uuid']);
            $table->dropForeign(['type_of_document_uuid']);
        });
    }
};
