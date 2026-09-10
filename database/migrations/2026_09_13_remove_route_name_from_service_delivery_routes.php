<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_delivery_control_sheet_routes') && Schema::hasColumn('service_delivery_control_sheet_routes', 'route_name')) {
            Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
                $table->dropColumn('route_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_delivery_control_sheet_routes') && ! Schema::hasColumn('service_delivery_control_sheet_routes', 'route_name')) {
            Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
                $table->string('route_name', 255)->nullable()->after('order_index');
            });
        }
    }
};
