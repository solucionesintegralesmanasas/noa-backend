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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('document_type_uuid')->references('uuid')->on('type_of_documents')->onUpdate('cascade');
            $table->foreign('tax_regime_uuid')->references('uuid')->on('tax_regimes')->onUpdate('cascade');
            $table->foreign('municipality_uuid')->references('uuid')->on('cities')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['document_type_uuid']);
            $table->dropForeign(['tax_regime_uuid']);
            $table->dropForeign(['municipality_uuid']);
        });
    }
};
