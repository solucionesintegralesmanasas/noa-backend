<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id()->comment('Identificador interno autoincremental');
            $table->uuid('uuid')->unique()->comment('UUID v7 único del contrato');
            $table->uuid('company_uuid')->comment('UUID de la empresa');
            $table->uuid('third_party_uuid')->comment('UUID del empleado (tercero)');
            
            $table->enum('contract_type', ['TERMINO_FIJO', 'TERMINO_INDEFINIDO', 'OBRA_LABOR', 'PRESTACION_SERVICIOS', 'APRENDIZAJE'])->comment('Tipo de vinculación laboral');
            $table->date('start_date')->comment('Fecha de inicio del contrato');
            $table->date('end_date')->nullable()->comment('Fecha de finalización (si aplica)');
            $table->decimal('base_salary', 18, 2)->comment('Salario base mensual');
            $table->enum('salary_type', ['ORDINARIO', 'INTEGRAL'])->default('ORDINARIO')->comment('Naturaleza salarial');
            $table->boolean('transport_subsidy_applies')->default(true)->comment('Indica si aplica auxilio de transporte');
            $table->decimal('working_hours_per_week', 5, 2)->nullable()->default(47.00)->comment('Horas semanales pactadas');
            $table->enum('status', ['ACTIVO', 'SUSPENDIDO', 'TERMINADO'])->default('ACTIVO')->comment('Estado del contrato');
            $table->string('termination_reason', 255)->nullable()->comment('Motivo de terminación');
            
            $table->timestamps();

            // Índices y llaves foráneas
            $table->index('third_party_uuid', 'idx_third_party_uuid');
            $table->index('company_uuid', 'idx_company_uuid');

            $table->foreign('company_uuid', 'fk_contract_company')
                  ->references('uuid')->on('companies')
                  ->onDelete('cascade');

            $table->foreign('third_party_uuid', 'fk_contract_employee')
                  ->references('uuid')->on('third_parties')
                  ->onDelete('cascade');
        });
        
        DB::statement("ALTER TABLE `employment_contracts` comment 'Contratos laborales por empleado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
