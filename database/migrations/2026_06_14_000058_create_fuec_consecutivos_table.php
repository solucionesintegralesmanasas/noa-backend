<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuec_consecutives', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique()->comment('UUID universal del registro');
            $table->enum('type', ['contract', 'extract'])->comment('Tipo de consecutivo FUEC');
            $table->char('company_uuid', 36)->comment('UUID de la empresa propietaria del registro');
            $table->unsignedSmallInteger('year')->comment('Año para reinicio anual');
            $table->unsignedBigInteger('contract_id')->nullable()->comment('ID interno de tu tabla de contratos. NULL si el type es contract');
            $table->unsignedInteger('current_consecutive')->default(0)->comment('Último número asignado');
            $table->timestamps();

            // Clave única
            $table->unique(['type', 'year', 'contract_id'], 'uq_fuec_sequence');

            // Clave foránea
            $table->foreign('company_uuid', 'fk_fuec_consecutives_company')
                ->references('uuid')
                ->on('companies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuec_consecutives');
    }
};
