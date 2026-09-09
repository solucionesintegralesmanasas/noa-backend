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
        Schema::create('business_collaboration_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('company_uuid');
            $table->uuid('vehicle_uuid');
            $table->string('resolution_number', 50);
            $table->string('agreement_internal_id', 50)->unique();
            $table->string('contracting_entity_nit', 20);
            $table->string('contracting_entity_name', 255);
            $table->date('effective_date');
            $table->date('expiry_date');
            $table->string('rep_name', 150);
            $table->string('rep_document_id', 20);
            $table->enum('transport_modality', ['CARGA', 'ESPECIAL', 'PASAJEROS', 'MIXTO']);
            $table->integer('max_fleet_capacity')->nullable()->default(0);
            $table->boolean('status')->default(1);
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
        Schema::dropIfExists('business_collaboration_agreements');
    }
};
