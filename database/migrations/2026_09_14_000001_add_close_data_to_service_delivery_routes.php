<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_delivery_control_sheet_routes')) {
            return;
        }

        Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'end_time')) {
                $table->time('end_time')->nullable()->after('destination');
            }
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'ending_kilometer')) {
                $table->decimal('ending_kilometer', 12, 2)->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'number_of_tolls')) {
                $table->integer('number_of_tolls')->default(0)->nullable()->after('ending_kilometer');
            }
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'total_toll_value')) {
                $table->decimal('total_toll_value', 12, 2)->default(0)->nullable()->after('number_of_tolls');
            }
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'end_novelty')) {
                $table->string('end_novelty', 1000)->nullable()->after('total_toll_value');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_delivery_control_sheet_routes')) {
            return;
        }

        Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
            foreach (['end_time', 'ending_kilometer', 'number_of_tolls', 'total_toll_value', 'end_novelty'] as $column) {
                if (Schema::hasColumn('service_delivery_control_sheet_routes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};