<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Los campos número de serie y VIN son opcionales a nivel de
     * validación (Store/UpdateVehicleRequest los definen como nullable),
     * por lo que las columnas deben aceptar NULL.
     * Se usa SQL directo porque doctrine/dbal no está instalado.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `vehicles` MODIFY `serial_number` VARCHAR(50) NULL COMMENT 'Número de serie del vehículo'");
        DB::statement("ALTER TABLE `vehicles` MODIFY `vin_number` VARCHAR(50) NULL COMMENT 'Número de VIN del vehículo'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('vehicles')->whereNull('serial_number')->update(['serial_number' => '']);
        DB::table('vehicles')->whereNull('vin_number')->update(['vin_number' => '']);
        DB::statement("ALTER TABLE `vehicles` MODIFY `serial_number` VARCHAR(50) NOT NULL COMMENT 'Número de serie del vehículo'");
        DB::statement("ALTER TABLE `vehicles` MODIFY `vin_number` VARCHAR(50) NOT NULL COMMENT 'Número de VIN del vehículo'");
    }
};
