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
        Schema::create('maintenance', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->enum('maintenance_type', ['PREVENTIVA', 'CORRECTIVA', 'OTRO']);
            $table->unsignedInteger('mileage');
            $table->text('service_description');
            $table->string('mechanic_name', 150)->nullable();
            $table->string('workshop_name', 150)->nullable();
            $table->date('maintenance_date');
            $table->decimal('labor_cost', 12, 2)->nullable()->default(0);
            $table->decimal('parts_cost', 12, 2)->nullable()->default(0);
            $table->string('invoice_number', 50)->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['Pendiente', 'Finalizado', 'Anulado'])->default('Pendiente');
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
        Schema::dropIfExists('maintenance');
    }
};
