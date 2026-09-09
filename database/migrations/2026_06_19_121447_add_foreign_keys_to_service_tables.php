<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── service_delivery_control_sheet ───
        /* Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
            $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        }); */

        // ─── service_internal_controls ───
        Schema::table('service_internal_controls', function (Blueprint $table) {
            /* $table->foreign('company_uuid')
                ->references('uuid')
                ->on('companies')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('vehicle_uuid')
                ->references('uuid')
                ->on('vehicles')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('third_party_uuid')
                ->references('uuid')
                ->on('third_parties')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('fuec_uuid')
                ->references('uuid')
                ->on('fuecs')
                ->onDelete('cascade')
                ->onUpdate('cascade'); */

            /* $table->foreign('service_delivery_control_sheet_uuid', 'fk_sic_sdcs_uuid')
                ->references('uuid')
                ->on('service_delivery_control_sheet')
                ->onDelete('cascade')
                ->onUpdate('cascade'); */
        });

        // ─── service_internal_controls_subcontracted ───
        Schema::table('service_internal_controls_subcontracted', function (Blueprint $table) {
            $table->foreign('vehicle_class_uuid', 'fk_sics_vc_uuid')
                ->references('uuid')
                ->on('vehicle_class')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('service_delivery_control_sheet_uuid', 'fk_sics_sdcs_uuid')
                ->references('uuid')
                ->on('service_delivery_control_sheet')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        // ─── service_internal_controls_subcontracted ───
        Schema::table('service_internal_controls_subcontracted', function (Blueprint $table) {
            $table->dropForeign('fk_sics_vc_uuid');
            $table->dropForeign('fk_sics_sdcs_uuid');
        });

        // ─── service_internal_controls ───
        Schema::table('service_internal_controls', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['vehicle_uuid']);
            $table->dropForeign(['third_party_uuid']);
            $table->dropForeign(['fuec_uuid']);
            $table->dropForeign('fk_sic_sdcs_uuid');
        });

        // ─── service_delivery_control_sheet ───
        Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
        });
    }
};
