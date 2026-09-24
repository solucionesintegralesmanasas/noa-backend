<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evita digest duplicados ante ejecuciones concurrentes o reintentos:
     * un digest por empresa, franja y fecha; un recordatorio por entidad.
     * Las filas heredadas con NULL no colisionan (NULL distintos en MariaDB).
     */
    public function up(): void
    {
        Schema::table('email_notification_logs', function (Blueprint $table) {
            $table->unique(['company_uuid', 'milestone', 'sent_date'], 'email_logs_company_slot_date_unique');
            $table->unique(['entity_type', 'entity_uuid', 'milestone'], 'email_logs_entity_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_notification_logs', function (Blueprint $table) {
            $table->dropUnique('email_logs_company_slot_date_unique');
            $table->dropUnique('email_logs_entity_slot_unique');
        });
    }
};
