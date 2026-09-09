<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_internal_controls', function (Blueprint $table) {
            $table->id()->comment('Identificador único del estado');
            $table->uuid('uuid')->unique()->comment('UUID único universal del estado');
            $table->char('company_uuid', 36)->comment('UUID único universal de la empresa');
            $table->char('vehicle_uuid', 36)->comment('UUID único universal del vehículo');
            $table->char('third_party_uuid', 36)->comment('UUID único universal del conductor');
            $table->char('fuec_uuid', 36)->nullable()->comment('UUID único universal del FUEC');
            $table->char('service_delivery_control_sheet_uuid', 36)->comment('UUID único universal de la hoja de control');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();

            $table->comment('Estado de la hoja de control para servicios directos con la empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_internal_controls');
    }
};
