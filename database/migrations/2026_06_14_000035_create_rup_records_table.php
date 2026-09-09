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
        Schema::create('rup_records', function (Blueprint $table) {
            $table->id()->comment('Identificador único del RUP');
            $table->uuid('uuid')->unique()->comment('UUID universal único del RUP');
            $table->uuid('company_uuid')->comment('UUID de la empresa inscrita');
            $table->string('registration_number', 50)->comment('Número de inscripción en el RUP');
            $table->date('issue_date')->nullable()->comment('Fecha de expedición del certificado');
            $table->date('expiration_date')->nullable()->comment('Fecha de vencimiento del certificado');
            $table->decimal('legal_capacity_score', 15, 2)->nullable()->comment('Índice de capacidad jurídica');
            $table->decimal('financial_capacity_score', 15, 2)->nullable()->comment('Índice de capacidad financiera');
            $table->decimal('organizational_capacity_score', 15, 2)->nullable()->comment('Índice de capacidad organizacional');
            $table->decimal('contracting_capacity_score', 15, 2)->nullable()->comment('Índice de capacidad de contratación');
            $table->string('rup_certificate_path', 255)->nullable()->comment('Ruta del archivo digital del certificado RUP');
            $table->enum('status', ['VIGENTE', 'VENCIDO', 'RENOVADO', 'CANCELADO', 'SUSPENDIDO', 'NO_INSCRITO'])->nullable()->default('VIGENTE')->comment('Estado actual del registro RUP');
            $table->text('remarks')->nullable()->comment('Observaciones adicionales');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rup_records');
    }
};
