<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARQ-007/008: la tabla de planillas no tenía ningún índice por empresa y fecha, y el monitor de
 * flota, el historial y los reportes filtran siempre por (company_uuid, service_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('service_delivery_control_sheet', 'idx_sdcs_company_date')) {
            Schema::table('service_delivery_control_sheet', function (Blueprint $t) {
                $t->index(['company_uuid', 'service_date'], 'idx_sdcs_company_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('service_delivery_control_sheet', 'idx_sdcs_company_date')) {
            Schema::table('service_delivery_control_sheet', fn (Blueprint $t) => $t->dropIndex('idx_sdcs_company_date'));
        }
    }
};
