<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('rental_contract_uuid');
            $table->uuid('rental_installment_uuid');
            $table->date('discount_date');
            $table->string('concept', 255);
            $table->decimal('amount', 15, 2);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('restrict');
            $table->foreign('rental_contract_uuid')->references('uuid')->on('rental_contracts')->onDelete('cascade');
            $table->foreign('rental_installment_uuid')->references('uuid')->on('rental_installments')->onDelete('cascade');

            $table->index(['company_uuid', 'rental_contract_uuid'], 'rental_discounts_company_contract_idx');
            $table->index('rental_installment_uuid', 'rental_discounts_installment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_discounts');
    }
};
