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
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'funcionario_nombre')) {
                $table->string('funcionario_nombre', 150)->nullable()->after('destination');
            }
            if (! Schema::hasColumn('service_delivery_control_sheet_routes', 'funcionario_cc')) {
                $table->string('funcionario_cc', 30)->nullable()->after('funcionario_nombre');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_delivery_control_sheet_routes')) {
            return;
        }

        Schema::table('service_delivery_control_sheet_routes', function (Blueprint $table) {
            foreach (['funcionario_nombre', 'funcionario_cc'] as $column) {
                if (Schema::hasColumn('service_delivery_control_sheet_routes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
