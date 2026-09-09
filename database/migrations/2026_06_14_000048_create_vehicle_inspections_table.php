<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID único universal del resultado de la inspección');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('vehicle_uuid')->comment('UUID único universal del vehículo');
            $table->date('inspection_date')->comment('Fecha de la inspección');
            $table->string('inspector_name', 150)->nullable()->comment('Nombre del inspector');
            $table->unsignedInteger('mileage')->nullable()->comment('Kilometraje del vehículo');
            $table->uuid('driver_uuid')->nullable()->comment('UUID único universal del conductor');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_inspections');
    }
};
