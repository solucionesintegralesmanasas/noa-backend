<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rental_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->uuid('owner_uuid');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('monthly_term')->default(12)->comment('Plazo en meses');
            $table->decimal('monthly_fee', 15, 2)->comment('Canon mensual');
            $table->enum('status', ['active', 'finished', 'cancelled'])->default('active');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('restrict');
            $table->foreign('vehicle_uuid')->references('uuid')->on('vehicles')->onDelete('restrict');
            $table->foreign('owner_uuid')->references('uuid')->on('owners')->onDelete('restrict');
            $table->index(['company_uuid', 'status']);
            $table->index(['vehicle_uuid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_contracts');
    }
};
