<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-002 §4 — Cierre con excepción: una unidad operativa que no pudo completarse
 * por la vía normal, con motivo justificado y aprobación registrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_delivery_control_sheet', static function (Blueprint $table): void {
            $table->string('exception_reason', 255)->nullable()
                ->after('availability_reason')
                ->comment('Motivo por el que se cierra sin completar la evidencia');
            $table->char('exception_approved_by', 36)->nullable()
                ->after('exception_reason')
                ->comment('UUID del usuario que aprobó la excepción');
            $table->timestamp('exception_approved_at')->nullable()
                ->after('exception_approved_by')
                ->comment('Momento de la aprobación');
        });
    }

    public function down(): void
    {
        Schema::table('service_delivery_control_sheet', static function (Blueprint $table): void {
            $table->dropColumn(['exception_reason', 'exception_approved_by', 'exception_approved_at']);
        });
    }
};
