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
        Schema::create('occupational_safety_records', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro');
            $table->uuid('uuid')->unique()->comment('UUID universal único');
            $table->uuid('company_uuid')->comment('UUID de la empresa propietaria del registro');
            $table->integer('operation_year')->comment('Año de operación o reporte');
            $table->integer('fatalities')->nullable()->default(0)->comment('Número de fatalidades reportadas');
            $table->integer('incapacitating_accidents_count')->nullable()->default(0)->comment('Número de accidentes incapacitantes');
            $table->integer('total_incidents_count')->nullable()->default(0)->comment('Número total de incidentes');
            $table->integer('lost_days_count')->nullable()->default(0)->comment('Total de días de incapacidad');
            $table->integer('average_workers_count')->nullable()->default(0)->comment('Número promedio de trabajadores');
            $table->decimal('hours_worked', 18, 2)->nullable()->comment('Total de horas-hombre trabajadas');
            $table->date('arl_accident_certificate_date')->nullable()->comment('Fecha de expedición certificado de accidentalidad ARL');
            $table->string('risk_level', 50)->nullable()->comment('Nivel de riesgo asignado');
            $table->date('arl_affiliation_certificate_date')->nullable()->comment('Fecha expedición certificado de afiliación ARL');
            $table->string('sgsst_rating', 50)->nullable()->comment('Calificación del SG-SST');
            $table->date('sgsst_evaluation_date')->nullable()->comment('Fecha de evaluación del SG-SST');
            $table->string('sgsst_certificate_path', 255)->nullable()->comment('Ruta del archivo certificado de resultado SG-SST');
            $table->text('remarks')->nullable()->comment('Observaciones generales');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occupational_safety_records');
    }
};
