<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rediseña el registro de correos para el digest consolidado de vencimientos.
     * Antes registraba un hito por documento con FK a vehicle_documents, lo que
     * impedía registrar tarjetas de operación. Ahora registra por empresa, fecha
     * y franja (slot) para los digest, y por entidad para el recordatorio único.
     */
    public function up(): void
    {
        Schema::table('email_notification_logs', function (Blueprint $table) {
            $table->dropForeign(['document_uuid']);
            $table->dropColumn('document_uuid');
            $table->uuid('company_uuid')->nullable()->after('uuid')
                ->comment('Empresa a la que pertenece el digest enviado');
            $table->string('entity_type', 100)->nullable()->after('company_uuid')
                ->comment('Clase de la entidad para recordatorios por documento');
            $table->uuid('entity_uuid')->nullable()->after('entity_type')
                ->comment('UUID de la entidad para recordatorios por documento');
            $table->date('sent_date')->nullable()->after('milestone')
                ->comment('Fecha en que se envió el correo');
            $table->index(['company_uuid', 'milestone', 'sent_date'], 'email_logs_company_milestone_date_idx');
            $table->index(['entity_type', 'entity_uuid', 'milestone'], 'email_logs_entity_milestone_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_notification_logs', function (Blueprint $table) {
            $table->dropIndex('email_logs_company_milestone_date_idx');
            $table->dropIndex('email_logs_entity_milestone_idx');
            $table->dropColumn(['company_uuid', 'entity_type', 'entity_uuid', 'sent_date']);
            $table->foreignUuid('document_uuid')->constrained('vehicle_documents', 'uuid')->cascadeOnDelete();
        });
    }
};
