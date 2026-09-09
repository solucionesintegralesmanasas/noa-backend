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
        Schema::table('fuec', function (Blueprint $table) {
            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('contractor_uuid')
                ->references('uuid')
                ->on('contractors')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('object_contract_uuid')
                ->references('uuid')
                ->on('objects_contracts')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('main_conductor_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('secondary_conductor_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('tertiary_conductor_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuec', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['contractor_uuid']);
            $table->dropForeign(['vehicle_uuid']);
            $table->dropForeign(['object_contract_uuid']);
            $table->dropForeign(['main_conductor_uuid']);
            $table->dropForeign(['secondary_conductor_uuid']);
            $table->dropForeign(['tertiary_conductor_uuid']);
        });
    }
};
