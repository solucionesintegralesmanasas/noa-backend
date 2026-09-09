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
        Schema::disableForeignKeyConstraints();

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id()->comment('ID único del vehículo');
            $table->uuid('uuid')->unique()->comment('UUID único universal del vehículo');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('third_party_uuid')->nullable()->comment('UUID único universal del tercero');
            $table->string('vehicle_license_plate', 20)->comment('Placa del vehículo');
            $table->string('transit_license_number', 20)->comment('Número de la licencia de tránsito');
            $table->enum('type_of_service', ['PUBLICO', 'PARTICULAR'])->default('PUBLICO')->comment('Tipo de servicio del vehículo');
            $table->uuid('vehicle_class_uuid')->comment('UUID único universal de la clase de vehículo');
            $table->uuid('brand_uuid')->comment('UUID único universal de la marca');
            $table->string('line', 50)->comment('Línea del vehículo');
            $table->string('model', 10)->comment('Modelo del vehículo');
            $table->string('color', 30)->comment('Color del vehículo');
            $table->string('serial_number', 50)->comment('Número de serie del vehículo');
            $table->string('engine_number', 50)->comment('Número de motor del vehículo');
            $table->string('chassis_number', 50)->comment('Número de chasis del vehículo');
            $table->string('vin_number', 50)->comment('Número de VIN del vehículo');
            $table->string('engine_displacement', 20)->comment('Cilindraje del motor');
            $table->string('body_type', 30)->comment('Tipo de carrocería del vehículo');
            $table->enum('fuel_type', ['GASOLINA', 'DIESEL', 'GAS', 'ELECTRICIDAD', 'HIBRIDO', 'OTRO'])->comment('Tipo de combustible del vehículo');
            $table->date('registration_date')->comment('Fecha de registro del vehículo');
            $table->string('transit_authority', 50)->comment('Autoridad de tránsito del vehículo');
            $table->integer('doors')->comment('Número de puertas del vehículo');
            $table->integer('load_capacity')->comment('Capacidad de carga del vehículo');
            $table->integer('gross_vehicle_weight')->comment('Peso bruto vehicular');
            $table->integer('passenger_capacity')->comment('Capacidad de pasajeros del vehículo');
            $table->integer('seated_passenger_capacity')->comment('Capacidad de pasajeros sentados del vehículo');
            $table->integer('number_of_axles')->comment('Número de ejes del vehículo');
            $table->boolean('exact_payment')->default(0)->comment('Indica si el pago es exacto');
            $table->string('internal_number', 20)->nullable()->comment('Número interno del vehículo');
            $table->string('address_type', 100)->comment('Tipo de dirección');
            $table->string('steering_type', 30)->nullable()->comment('Tipo de dirección');
            $table->string('transmission_type', 30)->nullable()->comment('Tipo de transmisión');
            $table->string('number_of_speeds', 30)->nullable()->comment('Número de velocidades');
            $table->string('bearing_type', 30)->nullable()->comment('Tipo de rodamiento');
            $table->string('rear_suspension', 30)->nullable()->comment('Tipo de suspensión trasera');
            $table->string('number_of_tires', 20)->nullable()->comment('Número de llantas');
            $table->string('rim_size', 20)->nullable()->comment('Tamaño del rin');
            $table->string('rim_material', 20)->nullable()->comment('Material del rin');
            $table->string('front_brake_type', 30)->nullable()->comment('Tipo de freno delantero');
            $table->string('rear_brake_type', 30)->nullable()->comment('Tipo de freno trasero');
            $table->string('number_of_windows', 20)->nullable()->comment('Número de ventanas');
            $table->boolean('is_active')->default(1)->comment('Indica si el vehículo está activo');
            $table->timestamps();
        });

        Schema::create('vehicles_branches', function (Blueprint $table) {
            $table->id()->comment('ID único del vehículo');
            $table->uuid('uuid')->unique()->comment('UUID único universal del vehículo');
            $table->uuid('company_uuid')->comment('UUID único universal de la empresa');
            $table->uuid('vehicle_uuid')->comment('UUID único universal del vehículo');
            $table->uuid('branch_uuid')->comment('UUID único universal de la sucursal');
            $table->date('entry_date')->comment('Fecha de entrada');
            $table->date('exit_date')->comment('Fecha de salida');
            $table->enum('exit_type', ['ENTRADA', 'SALIDA', 'PRESTAMO'])->comment('Tipo de salida');
            $table->boolean('is_active')->default(1)->comment('Indica si el vehículo está activo');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('vehicles_branches');
        Schema::dropIfExists('vehicles');
        Schema::enableForeignKeyConstraints();
    }
};
