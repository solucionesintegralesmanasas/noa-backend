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
        Schema::create('driver_licenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID de la licencia');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->uuid('third_party_uuid')->comment('UUID del tercero');
            $table->string('number', 50)->comment('Numero de la licencia');
            $table->enum('category', ['C1', 'C2', 'C3'])->comment('Categoria de la licencia');
            $table->date('issue_date')->comment('Fecha de expedicion de la licencia');
            $table->date('expiration_date')->comment('Fecha de expiracion de la licencia');
            $table->string('restrictions', 255)->nullable();
            $table->string('status')->nullable()->default('ACTIVA');
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
        Schema::dropIfExists('driver_licenses');
    }
};
