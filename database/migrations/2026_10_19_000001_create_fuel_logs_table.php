<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Control de Combustible, Kilometraje y Saldos.
     * Tipos precisos: decimal(12,2) para dinero y galones, unsignedInteger para kilometrajes.
     */
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->uuid('driver_uuid')->nullable();

            $table->date('date');

            // Kilometraje
            $table->unsignedInteger('initial_odometer');
            $table->unsignedInteger('final_odometer');
            $table->unsignedInteger('recorrido');

            // Dinero y galones
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('fuel_price_per_gallon', 12, 2)->default(0);
            $table->decimal('gallons_pumped', 12, 2)->default(0);

            // Saldos de tanque (galones)
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->decimal('gallons_consumed', 12, 2)->default(0);
            $table->decimal('final_balance', 12, 2)->default(0);

            // Rendimiento promedio aplicado (km/gal) y métricas derivadas
            $table->decimal('average_performance', 12, 2)->nullable();
            $table->decimal('km_per_gallon', 12, 2)->nullable();
            $table->decimal('cost_per_km', 12, 2)->default(0);

            $table->timestamps();

            $table->index('company_uuid');
            $table->index('vehicle_uuid');
            $table->index('driver_uuid');
            $table->index(['vehicle_uuid', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
