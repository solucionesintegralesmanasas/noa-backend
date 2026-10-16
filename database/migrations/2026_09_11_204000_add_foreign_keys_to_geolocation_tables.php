<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('driver_locations', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vehicle_uuid')->references('uuid')->on('vehicles')->onDelete('set null')->onUpdate('cascade');
        });

        Schema::table('driver_location_sessions', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vehicle_uuid')->references('uuid')->on('vehicles')->onDelete('set null')->onUpdate('cascade');
        });

        Schema::table('driver_location_alerts', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('driver_location_uuid')->references('uuid')->on('driver_locations')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('geofences', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
        });

        Schema::table('driver_locations', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['third_party_uuid']);
            $table->dropForeign(['vehicle_uuid']);
        });

        Schema::table('driver_location_sessions', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['third_party_uuid']);
            $table->dropForeign(['vehicle_uuid']);
        });

        Schema::table('driver_location_alerts', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['third_party_uuid']);
            $table->dropForeign(['driver_location_uuid']);
        });
    }
};