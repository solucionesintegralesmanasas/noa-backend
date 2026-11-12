<?php

declare(strict_types=1);

namespace App\Services;

use App\Traits\BelongsToCompany;
use App\Utils\Logger;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Servicio Web Cron para gestionar ejecución de tareas programadas.
 *
 * Proporciona métodos para controlar el Web Cron en entornos de hosting compartido
 * donde no hay acceso directo a un demonio de tareas programadas.
 *
 * El servicio usa un mecanismo de candado por archivo (`storage/framework/webcron.running`)
 * para prevenir múltiples ejecuciones concurrentes y controles de cadencia (`storage/framework/webcron.lock`).
 *
 * @author Claude Code
 *
 * @version 1.0.0
 *
 * @since 2026-11-11
 */
class WebCronService
{
    /**
     * Obtiene el estado actual del Web Cron.
     *
     * @return array Estado del Web Cron
     */
    public function getStatus(): array
    {
        $runningLock = storage_path('framework/webcron.running');
        $lockFile = storage_path('framework/webcron.lock');

        $isRunning = file_exists($runningLock) && $this->isProcessRunning($runningLock);
        $lockExists = file_exists($lockFile);
        $lastRunTime = $lockExists ? filemtime($lockFile) : null;
        $timeSinceLastRun = $lastRunTime ? time() - $lastRunTime : null;

        return [
            'is_running' => $isRunning,
            'lock_exists' => $lockExists,
            'last_run_time' => $lastRunTime,
            'time_since_last_run' => $timeSinceLastRun,
            'is_locked' => $this->isLocked(),
        ];
    }

    /**
     * Obtiene la configuración actual del Web Cron.
     *
     * @return array Configuración del Web Cron
     */
    public function getConfig(): array
    {
        $phpBinary = $this->detectPhpBinary();
        $lockTimeout = $this->getLockTimeout();

        return [
            'php_binary' => $phpBinary,
            'lock_timeout' => $lockTimeout,
            'lock_file' => storage_path('framework/webcron.lock'),
            'running_file' => storage_path('framework/webcron.running'),
            'cron_secret_configured' => (bool) config('services.cron.secret'),
        ];
    }

    /**
     * Ejecuta las tareas del Web Cron manualmente.
     *
     * @param bool $force Forzar ejecución aunque ya esté corriendo
     * @return array Resultado de la ejecución
     * @throws \InvalidArgumentException si ya está corriendo y no es force=true
     */
    public function run(bool $force = false): array
    {
        if ($this->isProcessRunning(storage_path('framework/webcron.running'))) {
            if (! $force) {
                throw new \InvalidArgumentException('Web Cron ya está en ejecución.');
            }

            // Si está forzando y ya está corriendo, detener primero
            $this->stop();
        }

        $runningLock = storage_path('framework/webcron.running');
        $fp = @fopen($runningLock, 'c+');

        if (! $fp || ! @flock($fp, LOCK_EX | LOCK_NB)) {
            throw new \InvalidArgumentException('Web Cron ya está en ejecución en otro proceso.');
        }

        $lockFile = storage_path('framework/webcron.lock');

        try {
            // Ejecutar las tareas programadas
            $this->executeCronTasks();

            @touch($lockFile);

            return [
                'success' => true,
                'message' => 'Web Cron completado con éxito.',
                'timestamp' => time(),
            ];
        } catch (\Throwable $e) {
            // Si falla, liberar el candado para permitir reintentar en la siguiente petición
            @unlink($lockFile);

            Logger::error('WebCron falló: ' . $e->getMessage(), $e);

            throw $e;
        } finally {
            if ($fp) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
        }
    }

    /**
     * Detiene la ejecución del Web Cron.
     *
     * @return array Resultado de la detención
     * @throws \InvalidArgumentException si no hay proceso activo
     */
    public function stop(): array
    {
        $runningLock = storage_path('framework/webcron.running');

        if (! file_exists($runningLock)) {
            throw new \InvalidArgumentException('No hay proceso activo del Web Cron.');
        }

        @unlink($runningLock);

        return [
            'success' => true,
            'message' => 'Web Cron detenido con éxito.',
            'timestamp' => time(),
        ];
    }

    /**
     * Ejecuta las tareas programadas del Web Cron.
     *
     * @throws Throwable si falla la ejecución de tareas
     */
    private function executeCronTasks(): void
    {
        // Usar el trait para ejecutar sin contexto de empresa (MULTIPLE_TENANTS_DISABLED)
        BelongsToCompany::withoutCompanyScope(function () {
            // Importar los servicios necesarios
            $emailLogService = app('\App\Services\Notifications\EmailLogService');
            $notificationsService = app('\App\Services\Notifications\NotificationsService');
            $socialSecurityService = app('\App\Services\Fleet\SocialSecurityContributionService');

            $emailLogService->notifyExpiringDocuments();
            $notificationsService->syncNotifications();
            $socialSecurityService->autoUpdateExpiredStatuses();
        });
    }

    /**
     * Verifica si el Web Cron está bloqueado (tiempo sin ejecutar).
     *
     * @return bool true si está bloqueado
     */
    private function isLocked(): bool
    {
        $lockFile = storage_path('framework/webcron.lock');

        if (! file_exists($lockFile)) {
            return false;
        }

        $mtime = (int) (@filemtime($lockFile) ?: 0);
        $now = time();

        // Está bloqueado si el archivo tiene menos de 1 hora de antigüedad
        return ($now - $mtime) < 3600;
    }

    /**
     * Detecta el binario PHP CLI para ejecutar comandos.
     *
     * @return string|null path del binario PHP CLI o null si no se encuentra
     */
    private function detectPhpBinary(): ?string
    {
        // Intentar la configuración primero
        $configured = config('services.webcron.php_binary');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        // Autodetección
        $executable = 'php' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        $candidates = [
            rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $executable,
            dirname(PHP_BINARY, 3) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $executable,
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $executable,
            '/usr/bin/' . $executable,
            '/usr/local/bin/' . $executable,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Obtiene el timeout de bloqueo para el Web Cron.
     *
     * @return int tiempo de espera en segundos (1 hora)
     */
    private function getLockTimeout(): int
    {
        return 3600;
    }

    /**
     * Verifica si un proceso del Web Cron está corriendo.
     *
     * @param string $lockFile path al archivo de candado
     * @return bool true si está corriendo
     */
    private function isProcessRunning(string $lockFile): bool
    {
        $fp = @fopen($lockFile, 'c+');

        if (! $fp) {
            return false;
        }

        $isRunning = @flock($fp, LOCK_EX | LOCK_NB);
        @fclose($fp);

        return $isRunning;
    }
}