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
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->enum('document_type', ['SOAT', 'RCE', 'RCC', 'RTM']);
            $table->string('policy_number', 200);
            $table->date('issue_date');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date');
            $table->string('issuing_entity', 200);
            $table->string('tariff_code')->nullable();
            $table->string('taker', 200)->nullable();
            $table->enum('status', ['SI', 'NO', 'VIGENTE', 'INACTIVA', 'NO VIGENTE']);
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
        Schema::dropIfExists('vehicle_documents');
    }
};
