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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('brand_uuid')->references('uuid')->on('brands')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vehicle_class_uuid')->references('uuid')->on('vehicle_class')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('third_party_uuid')->references('uuid')->on('third_parties')->onDelete('set null')->onUpdate('cascade');
        });

        Schema::table('vehicles_branches', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vehicle_uuid')->references('uuid')->on('vehicles')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('branch_uuid')->references('uuid')->on('branches')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('vehicles_branches')) {
            Schema::table('vehicles_branches', function (Blueprint $table) {
                try {
                    $table->dropForeign(['company_uuid']);
                } catch (Exception $e) {
                }
                try {
                    $table->dropForeign(['vehicle_uuid']);
                } catch (Exception $e) {
                }
                try {
                    $table->dropForeign(['branch_uuid']);
                } catch (Exception $e) {
                }
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                try {
                    $table->dropForeign(['brand_uuid']);
                } catch (Exception $e) {
                }
                try {
                    $table->dropForeign(['vehicle_class_uuid']);
                } catch (Exception $e) {
                }
                try {
                    $table->dropForeign(['branch_uuid']);
                } catch (Exception $e) {
                }
                try {
                    $table->dropForeign(['company_uuid']);
                } catch (Exception $e) {
                }
                try {
                    $table->dropForeign(['third_party_uuid']);
                } catch (Exception $e) {
                }
            });
        }
    }
};
