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
        Schema::create('branches', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la sucursal');
            $table->uuid('uuid')->unique()->comment('UUID universal único de la sucursal');
            $table->uuid('company_uuid')->comment('UUID de la empresa propietaria');
            $table->string('name', 100)->comment('Nombre comercial o interno de la sucursal');
            $table->string('address', 200)->comment('Dirección física de la sucursal');
            $table->uuid('municipality_uuid')->comment('UUID del municipio de ubicación');
            $table->boolean('is_primary')->nullable()->default(false)->comment('Indica si es la sede principal de la empresa');
            $table->boolean('status')->default(1)->comment('Estado operativo de la sucursal');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
