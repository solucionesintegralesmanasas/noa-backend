<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_delivery_control_sheet', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');
            $table->uuid('uuid')->unique()->comment('UUID único universal del registro');
            $table->char('company_uuid', 36)->comment('UUID único universal de la empresa');
            $table->string('official_name_and_surname', 255)->nullable()->comment('Nombre y apellido del funcionario');
            $table->date('service_date')->comment('Fecha del servicio');
            $table->string('daily_route', 255)->nullable()->comment('Ruta diaria');
            $table->time('start_time')->nullable()->comment('Hora de inicio');
            $table->time('end_time')->nullable()->comment('Hora final');
            $table->time('total_hours')->nullable()->comment('Total horas');
            $table->string('starting_kilometer', 10)->nullable()->comment('Kilometraje inicial');
            $table->string('ending_kilometer', 10)->nullable()->comment('Kilometraje final');
            $table->unsignedInteger('number_of_tolls')->nullable()->comment('Numero de peajes');
            $table->decimal('total_toll_value', 10, 2)->nullable()->comment('Valor total peajes');
            $table->enum('type_of_control_sheet', [
                'DIRECTO_CON_LA_EMPRESA',
                'SUBCONTRATADO',
            ])->nullable()->comment('Tipo de control de hoja');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();

            $table->comment('Hoja de control de entrega de servicios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_delivery_control_sheet');
    }
};
