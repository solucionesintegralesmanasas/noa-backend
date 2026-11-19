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
        Schema::create('rental_installments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('rental_contract_uuid');
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('previous_balance', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2);
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('restrict');
            $table->foreign('rental_contract_uuid')->references('uuid')->on('rental_contracts')->onDelete('cascade');
            $table->unique(['rental_contract_uuid', 'installment_number'], 'uk_contract_period');
            $table->index(['rental_contract_uuid', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_installments');
    }
};
