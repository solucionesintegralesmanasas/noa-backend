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
        Schema::table('procedures', function (Blueprint $table) {
            $table->foreign('city_uuid')
                ->references('uuid')
                ->on('cities')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('territorial_director_uuid')
                ->references('uuid')
                ->on('territorial_directors')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('third_party_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropForeign(['city_uuid']);
            $table->dropForeign(['territorial_director_uuid']);
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['third_party_uuid']);
            $table->dropForeign(['vehicle_uuid']);
        });
    }
};
