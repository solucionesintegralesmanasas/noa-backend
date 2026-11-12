<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinatarios de Telegram para las alertas de documentos vencidos.
 *
 * @author Darwin Montes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            // Lista separada por comas (máximo 5 chat_id); el parseo vive en
            // SystemConfiguration::destinosTelegram().
            $table->string('notification_telegram_chat_ids', 500)->nullable()->after('notification_email');
            $table->boolean('notify_by_telegram')->default(false)->after('notify_by_email');
        });
    }

    public function down(): void
    {
        Schema::table('system_configuration', function (Blueprint $table) {
            $table->dropColumn(['notification_telegram_chat_ids', 'notify_by_telegram']);
        });
    }
};
