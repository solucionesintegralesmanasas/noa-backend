<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_delivery_control_sheet_routes') && Schema::hasColumn('service_delivery_control_sheet_routes', 'distance_km')) {
            Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
                $table->dropColumn('distance_km');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_delivery_control_sheet_routes') && ! Schema::hasColumn('service_delivery_control_sheet_routes', 'distance_km')) {
            Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
                $table->decimal('distance_km', 8, 2)->nullable()->after('destination');
            });
        }
    }
};
