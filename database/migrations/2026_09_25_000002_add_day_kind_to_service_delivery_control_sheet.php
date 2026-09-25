<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-002 §5.2 — Declaración explícita del día en disponibilidad y su motivo.
 *
 * Antes "disponibilidad" se INFERÍA de "la planilla no tiene recorridos", lo que
 * confundía dos situaciones opuestas: un día declarado en disponibilidad y un día
 * al que simplemente aún no se le cargaron rutas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_delivery_control_sheet', static function (Blueprint $table): void {
            $table->enum('day_kind', ['operacion', 'disponibilidad'])
                ->default('operacion')
                ->after('type_of_control_sheet')
                ->comment('Declaración del día; por defecto operación');
            $table->string('availability_reason', 255)->nullable()
                ->after('day_kind')
                ->comment('Motivo de la jornada en disponibilidad (obligatorio si day_kind=disponibilidad)');
        });

        // Índice parcial no es portable en MySQL; se indexa la combinación ligera
        // que usan los listados por proyecto/fecha.
        Schema::table('service_delivery_control_sheet', static function (Blueprint $table): void {
            $table->index(['day_kind'], 'idx_sdc_day_kind');
        });
    }

    public function down(): void
    {
        Schema::table('service_delivery_control_sheet', static function (Blueprint $table): void {
            $table->dropIndex('idx_sdc_day_kind');
            $table->dropColumn(['day_kind', 'availability_reason']);
        });
    }
};
