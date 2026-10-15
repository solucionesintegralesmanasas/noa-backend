<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro mayor (append-only) de los movimientos de cupos de capacidad transportadora.
 *
 * Cada descuento o liberación de un cupo queda registrado aquí como fuente de
 * verdad auditable: qué empresa, qué vehículo, qué trámite/resolución lo originó
 * y cómo quedó el saldo. `conveyor_capacity.current_capacity` se modifica solo a
 * través de este flujo (CapacityMovementService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacity_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID universal del movimiento');
            $table->uuid('company_uuid')->index()->comment('Empresa afectada por el movimiento');
            $table->uuid('conveyor_capacity_uuid')->nullable()->comment('Capacidad transportadora afectada');
            $table->uuid('vehicle_uuid')->nullable()->index()->comment('Vehículo que ocupa o libera el cupo');
            $table->string('vehicle_type', 100)->nullable()->comment('Tipo de vehículo usado para resolver la capacidad');
            $table->uuid('procedure_uuid')->nullable()->index()->comment('Trámite (paso) que originó el movimiento');
            $table->uuid('operation_card_uuid')->nullable()->index()->comment('Tarjeta de operación vinculada al movimiento');
            $table->enum('movement_type', [
                'DESCUENTO_NUEVO',
                'DESCUENTO_INGRESO',
                'LIBERACION_DESVINCULACION',
                'LIBERACION_SALIDA',
                'AJUSTE_MANUAL',
            ])->comment('Naturaleza del movimiento');
            $table->unsignedInteger('quantity')->default(1)->comment('Cupos movidos (siempre positivo)');
            $table->integer('capacity_before')->nullable()->comment('Saldo de cupos antes del movimiento');
            $table->integer('capacity_after')->nullable()->comment('Saldo de cupos después del movimiento');
            $table->boolean('applied')->default(true)->comment('true si mutó el cupo; false en modo auditoría');
            $table->string('resolution_number', 30)->nullable()->comment('Resolución (desvinculación) asociada');
            $table->text('notes')->nullable()->comment('Observaciones del movimiento');
            $table->uuid('created_by')->nullable()->comment('Usuario que ejecutó el movimiento');
            $table->timestamps();

            // Idempotencia: un mismo paso de trámite no puede movilizar dos veces el mismo tipo de cupo.
            $table->unique(['procedure_uuid', 'movement_type'], 'uniq_capacity_movement_procedure');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_movements');
    }
};
