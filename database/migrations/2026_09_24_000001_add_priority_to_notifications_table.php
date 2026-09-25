<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la prioridad a las notificaciones para separar las alertas de
     * vehículos de la empresa propia (PRIORITARIA) de las normales.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('priority', ['PRIORITARIA', 'NORMAL'])->default('NORMAL')->after('status')
                ->comment('Prioridad de la alerta: propia (vehículo de la empresa) o normal');
            $table->index(['company_uuid', 'status', 'priority', 'created_at'], 'notifications_company_status_priority_idx');
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_company_status_priority_idx');
            $table->dropColumn('priority');
        });
    }
};
