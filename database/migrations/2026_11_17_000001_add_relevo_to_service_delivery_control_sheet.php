<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relevo de conductor como cambio de turno (PCP).
 *
 * `turno` numera los tramos del mismo vehículo y fecha y `relevo_de_uuid`
 * apunta al tramo anterior. Además se agregan los índices que faltaban en
 * `service_internal_controls`: el relevo y el detalle del proyecto buscan por
 * hoja y por vehículo, y hoy esa tabla solo tiene PK y `uuid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_delivery_control_sheet', 'turno')) {
            Schema::table('service_delivery_control_sheet', function (Blueprint $t) {
                $t->unsignedTinyInteger('turno')->default(1)->after('retirada_de_proyecto');
            });
        }

        if (! Schema::hasColumn('service_delivery_control_sheet', 'relevo_de_uuid')) {
            Schema::table('service_delivery_control_sheet', function (Blueprint $t) {
                $t->char('relevo_de_uuid', 36)->nullable()->after('turno');
                $t->index('relevo_de_uuid', 'idx_sdcs_relevo_de');
                $t->foreign('relevo_de_uuid')
                    ->references('uuid')
                    ->on('service_delivery_control_sheet')
                    ->nullOnDelete()
                    ->onUpdate('cascade');
            });
        }

        if (! Schema::hasIndex('service_internal_controls', 'idx_sic_sheet')) {
            Schema::table('service_internal_controls', function (Blueprint $t) {
                $t->index('service_delivery_control_sheet_uuid', 'idx_sic_sheet');
            });
        }

        if (! Schema::hasIndex('service_internal_controls', 'idx_sic_vehicle')) {
            Schema::table('service_internal_controls', function (Blueprint $t) {
                $t->index('vehicle_uuid', 'idx_sic_vehicle');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('service_internal_controls', 'idx_sic_vehicle')) {
            Schema::table('service_internal_controls', fn (Blueprint $t) => $t->dropIndex('idx_sic_vehicle'));
        }

        if (Schema::hasIndex('service_internal_controls', 'idx_sic_sheet')) {
            Schema::table('service_internal_controls', fn (Blueprint $t) => $t->dropIndex('idx_sic_sheet'));
        }

        if (Schema::hasColumn('service_delivery_control_sheet', 'relevo_de_uuid')) {
            Schema::table('service_delivery_control_sheet', function (Blueprint $t) {
                $t->dropForeign(['relevo_de_uuid']);
                $t->dropIndex('idx_sdcs_relevo_de');
                $t->dropColumn('relevo_de_uuid');
            });
        }

        if (Schema::hasColumn('service_delivery_control_sheet', 'turno')) {
            Schema::table('service_delivery_control_sheet', fn (Blueprint $t) => $t->dropColumn('turno'));
        }
    }
};
