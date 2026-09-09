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
        Schema::create('experiences', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la experiencia');
            $table->uuid('uuid')->unique()->comment('UUID universal único de la experiencia');
            $table->uuid('company_uuid')->comment('UUID de la empresa que realizó el contrato');
            $table->string('customer_name', 255)->comment('Razón social o nombre del cliente');
            $table->decimal('value_before_tax', 18, 2)->nullable()->comment('Valor del contrato antes de impuestos');
            $table->string('currency', 10)->nullable()->default('COP')->comment('Moneda de contratación');
            $table->date('start_date')->nullable()->comment('Fecha de inicio de ejecución');
            $table->date('end_date')->nullable()->comment('Fecha de finalización o corte contractual');
            $table->date('is_ongoing')->nullable()->comment('Fecha hasta la cual la experiencia está vigente (NULL si finalizada)');
            $table->text('remarks')->nullable()->comment('Observaciones sobre alcance, cumplimiento o desempeño');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
