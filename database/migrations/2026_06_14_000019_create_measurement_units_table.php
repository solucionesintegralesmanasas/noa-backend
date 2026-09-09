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
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->comment('UUID V4 DE LA UNIDAD DE MEDIDA')->unique();
            $table->string('code', 10)->comment('CÓDIGO UN/CEFACT O DIAN (EJ. UND, KG, LT, HUR)')->unique();
            $table->string('name', 100)->comment('NOMBRE DESCRIPTIVO DE LA UNIDAD');
            $table->boolean('is_active')->default(1)->comment('ESTADO DE HABILITACIÓN PARA TRANSACCIONES');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE `measurement_units` COMMENT = 'Catálogo estandarizado de unidades de medida para facturación e inventario'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
    }
};
