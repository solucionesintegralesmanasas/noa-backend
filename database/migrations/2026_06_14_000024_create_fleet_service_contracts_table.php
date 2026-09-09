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
        Schema::create('fleet_service_contracts', function (Blueprint $table) {
            $table->id()->comment('Identificador único del contrato de servicio de flota');
            $table->uuid('uuid')->unique()->comment('UUID único universal del contrato');
            $table->uuid('procedure_uuid')->comment('UUID del procedimiento asociado al contrato');
            $table->unsignedInteger('item')->comment('Número de ítem o secuencia del contrato');
            $table->enum('type_of_action', ['C', 'M', 'E'])->comment('Tipo de acción: C=Creación, M=Modificación, E=Extensión');
            $table->date('issue_date')->comment('Fecha de emisión del contrato');
            $table->date('start_date')->comment('Fecha de inicio de vigencia del contrato');
            $table->date('end_date')->comment('Fecha de finalización de vigencia del contrato');
            $table->unsignedInteger('duration')->comment('Duración en días del contrato');
            $table->enum('contract_type', ['1', '2'])->comment('Tipo de contrato: 1=Vinculación, 2=Desvinculación');
            $table->string('contract_number', 50)->comment('Número único del contrato de servicio');
            $table->char('signature_validation', 5)->nullable()->default('S')->comment('Validación de firma electrónica (S=Sí, N=No)');
            $table->decimal('valuation_amount', 10, 2)->comment('Monto de valoración o avalúo del contrato');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_service_contracts');
    }
};
