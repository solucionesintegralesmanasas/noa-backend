<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_internal_controls_subcontracted', function (Blueprint $table) {
            $table->id()->comment('Identificador único del estado');
            $table->uuid('uuid')->unique()->comment('UUID único universal del estado');
            $table->char('vehicle_class_uuid', 36)->comment('UUID único universal de la clase del vehículo');
            $table->char('service_delivery_control_sheet_uuid', 36)->comment('UUID único universal de la hoja de control');
            $table->string('vehicle_license_plate', 20)->comment('Placa del vehículo');
            $table->string('driver_name_and_surname', 255)->comment('Nombre y apellido del conductor');
            $table->string('driver_license_number', 20)->comment('Número de licencia del conductor');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();

            $table->comment('Estado de la hoja de control para servicios subcontratados');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_internal_controls_subcontracted');
    }
};
