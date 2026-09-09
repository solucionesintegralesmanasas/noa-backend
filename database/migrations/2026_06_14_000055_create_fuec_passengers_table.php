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
        Schema::create('fuec_passengers', function (Blueprint $table) {
            $table->id()->comment('Identificador único del pasajero del FUEC');
            $table->uuid('uuid')->unique()->comment('UUID único universal del pasajero del FUEC');
            $table->uuid('fuec_uuid')->index()->comment('UUID único universal del FUEC');
            $table->uuid('type_of_document_uuid')->comment('UUID único universal del tipo de documento');
            $table->string('document_number', 20)->index()->comment('Número de documento del pasajero del FUEC');
            $table->string('first_and_last_name')->comment('Nombre y apellido del pasajero del FUEC');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuec_passengers');
    }
};
