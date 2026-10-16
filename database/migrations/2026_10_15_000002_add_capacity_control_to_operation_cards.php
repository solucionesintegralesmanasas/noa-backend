<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica la tarjeta de operación y le da estado frente al RUNT.
 *
 * - `operation_card_type` distingue por qué se emite la tarjeta: una NUEVA o un
 *   ingreso por CAMBIO_DE_EMPRESA consumen cupo; una RENOVACION, DUPLICADO o
 *   MODIFICACION no lo mueven (el cupo ya estaba ocupado por el mismo vehículo).
 * - `runt_status` permite cancelar/inhabilitar la tarjeta cuando se libera el
 *   cupo (desvinculación o salida por cambio de empresa).
 * - `procedure_uuid` enlaza la tarjeta con su expediente de radicación.
 *
 * El valor por defecto `RENOVACION` mantiene compatibles las filas e inserciones
 * que no declaran el tipo (no exigen movimiento de cupo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_cards', function (Blueprint $table) {
            $table->enum('operation_card_type', [
                'NUEVA',
                'RENOVACION',
                'CAMBIO_DE_EMPRESA',
                'DUPLICADO',
                'MODIFICACION',
            ])->default('RENOVACION')->after('operating_card_number')->comment('Motivo de emisión de la tarjeta');

            $table->enum('runt_status', ['ACTIVA', 'CANCELADA', 'SUSPENDIDA'])
                ->default('ACTIVA')->after('operation_card_type')->comment('Estado de la tarjeta frente al RUNT');

            $table->uuid('procedure_uuid')->nullable()->after('runt_status')->comment('Expediente de radicación asociado');
            $table->timestamp('cancelled_at')->nullable()->after('procedure_uuid')->comment('Fecha de cancelación de la tarjeta');
            $table->string('cancellation_reason', 255)->nullable()->after('cancelled_at')->comment('Motivo de cancelación');

            $table->index(['vehicle_uuid', 'runt_status'], 'idx_operation_cards_vehicle_runt');
        });
    }

    public function down(): void
    {
        Schema::table('operation_cards', function (Blueprint $table) {
            $table->dropIndex('idx_operation_cards_vehicle_runt');
            $table->dropColumn([
                'operation_card_type',
                'runt_status',
                'procedure_uuid',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
