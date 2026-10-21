<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitudes de tanqueo con aprobación de la empresa y QR de validación.
     */
    public function up(): void
    {
        Schema::create('fuel_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->uuid('driver_uuid')->nullable();
            $table->uuid('requested_by_user_uuid')->nullable();

            $table->string('code', 30)->unique();
            $table->string('public_token', 64)->unique();
            $table->string('status', 20)->default('PENDIENTE');

            $table->timestamp('requested_at')->useCurrent();
            $table->uuid('approved_by_user_uuid')->nullable();
            $table->string('approved_by_name', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by_user_uuid')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->uuid('fuel_log_uuid')->nullable();

            $table->json('snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_uuid', 'status']);
            $table->index(['vehicle_uuid', 'status']);
            $table->index('driver_uuid');
            $table->index('code');
            $table->index('public_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_requests');
    }
};
