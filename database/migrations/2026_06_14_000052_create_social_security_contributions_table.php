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
        Schema::create('social_security_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('third_party_uuid');
            $table->date('billing_period');
            $table->string('pila_pin', 50);
            $table->enum('contribution_type', ['E', 'Y', 'I', 'S'])->nullable()->default('E');
            $table->decimal('ibc_amount', 15, 2)->nullable();
            $table->boolean('health_paid')->nullable()->default(1);
            $table->boolean('pension_paid')->nullable()->default(1);
            $table->boolean('risk_labor_paid')->nullable()->default(1);
            $table->boolean('compensation_fund_paid')->nullable()->default(1);
            $table->string('eps_name', 100)->nullable();
            $table->date('eps_affiliation_date')->nullable();
            $table->string('pension_name', 100)->nullable();
            $table->date('pension_affiliation_date')->nullable();
            $table->string('risk_labor_name', 100)->nullable();
            $table->date('risk_labor_affiliation_date')->nullable();
            $table->string('compensation_fund_name', 100)->nullable();
            $table->date('compensation_fund_affiliation_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('status')->nullable()->default('PENDIENTE');
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('social_security_contributions');
    }
};
