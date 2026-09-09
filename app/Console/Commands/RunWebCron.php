<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fleet\SocialSecurityContributionService;
use App\Services\Notifications\EmailLogService;
use App\Services\Notifications\NotificationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta las tareas programadas del WebCron en un proceso aparte.
 *
 * Este comando es invocado por WebCronMiddleware a través de un proceso
 * hijo de PHP, de modo que las peticiones HTTP del usuario no se bloquean
 * mientras las notificaciones se procesan.
 */
class RunWebCron extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webcron:run';

    /**
     * @var string
     */
    protected $description = 'Ejecuta las tareas automáticas del WebCron (notificaciones y sincronizaciones)';

    /**
     * Tiempo de vida del candado en minutos.
     */
    private const LOCK_MINUTES = 60;

    public function handle(
        EmailLogService $emailLogService,
        NotificationsService $notificationsService,
        SocialSecurityContributionService $socialSecurityContributionService
    ): int {
        if (! Cache::add('last_web_cron_run_lock', true, now()->addMinutes(self::LOCK_MINUTES))) {
            $this->line('WebCron ya fue ejecutado recientemente. Se omite la ejecución.');

            return self::SUCCESS;
        }

        try {
            Log::info('WebCron: Iniciando ejecución automática en background...');

            $emailLogService->notifyExpiringDocuments();
            $notificationsService->syncNotifications();
            $socialSecurityContributionService->autoUpdateExpiredStatuses();

            Log::info('WebCron: Ejecución automática completada con éxito.');
            $this->info('WebCron completado con éxito.');
        } catch (\Throwable $e) {
            Log::error('WebCron falló: '.$e->getMessage());
            // Si falla, liberamos el candado para permitir reintentar en la siguiente petición
            Cache::forget('last_web_cron_run_lock');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}