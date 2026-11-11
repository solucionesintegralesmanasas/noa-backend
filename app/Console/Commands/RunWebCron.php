<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fleet\SocialSecurityContributionService;
use App\Services\Notifications\EmailLogService;
use App\Services\Notifications\NotificationsService;
use App\Traits\BelongsToCompany;
use Illuminate\Console\Command;
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

    public function handle(
        EmailLogService $emailLogService,
        NotificationsService $notificationsService,
        SocialSecurityContributionService $socialSecurityContributionService
    ): int {
        $runningLock = storage_path('framework/webcron.running');
        $fp = @fopen($runningLock, 'c+');

        if (! $fp || ! @flock($fp, LOCK_EX | LOCK_NB)) {
            $this->line('WebCron ya está en ejecución en otro proceso. Se omite.');
            if ($fp) {
                @fclose($fp);
            }

            return self::SUCCESS;
        }

        $lockFile = storage_path('framework/webcron.lock');

        try {
            Log::info('WebCron: Iniciando ejecución automática en background...');

            BelongsToCompany::withoutCompanyScope(function () use (
                $emailLogService,
                $notificationsService,
                $socialSecurityContributionService
            ) {
                $emailLogService->notifyExpiringDocuments();
                $notificationsService->syncNotifications();
                $socialSecurityContributionService->autoUpdateExpiredStatuses();
            });

            Log::info('WebCron: Ejecución automática completada con éxito.');
            @touch($lockFile);
            $this->info('WebCron completado con éxito.');
        } catch (\Throwable $e) {
            Log::error('WebCron falló: ' . $e->getMessage());
            // Si falla, liberamos el candado para permitir reintentar en la siguiente petición
            @unlink($lockFile);

            return self::FAILURE;
        } finally {
            if ($fp) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
        }

        return self::SUCCESS;
    }
}
