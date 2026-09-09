<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('service_date')->comment('Fecha de inicio del servicio (puede abarcar varios días)');
            $table->date('end_date')->nullable()->after('start_date')->comment('Fecha de fin del servicio. Null o igual a start_date = servicio de un día');
            $table->char('parent_uuid', 36)->nullable()->after('uuid')->comment('UUID del registro padre para servicios multi-día');
            $table->foreign('parent_uuid')->references('uuid')->on('service_delivery_control_sheet')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
            $table->dropForeign(['parent_uuid']);
            $table->dropColumn(['start_date', 'end_date', 'parent_uuid']);
        });
    }
};
