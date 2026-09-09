<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Middleware: WebCronMiddleware
 *
 * Simula la ejecución de un CRON en entornos de hosting compartido donde
 * no hay acceso a cPanel ni a la consola (CLI).
 *
 * La carga de trabajo se ejecuta en un proceso PHP hijo separado (comando
 * `webcron:run`) para que el worker de Apache/PHP no quede ocupado y las
 * peticiones de los usuarios no se bloqueen.
 */
class WebCronMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Se ejecuta DESPUÉS de que la respuesta ha sido enviada al navegador.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Si ya se ejecutó recientemente o ya hay un proceso previo, no hacemos nada
        if (Cache::has('last_web_cron_run_lock') || Cache::has('last_web_cron_spawn_lock')) {
            return;
        }

        // Candado corto para evitar lanzar dos procesos ante peticiones concurrentes
        if (! Cache::add('last_web_cron_spawn_lock', true, now()->addSeconds(90))) {
            return;
        }

        $this->runInBackground();
    }

    /**
     * Lanza el comando webcron:run en un proceso PHP independiente.
     */
    private function runInBackground(): void
    {
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/webcron.log');

        if (PHP_OS_FAMILY === 'Windows') {
            // 'start "" /B' ejecuta el comando en segundo plano sin abrir una ventana
            $command = sprintf(
                'start "" /B %s %s webcron:run --no-ansi > %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($artisan),
                escapeshellarg($logFile)
            );
        } else {
            $command = sprintf(
                '%s %s webcron:run --no-ansi > %s 2>&1 &',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($artisan),
                escapeshellarg($logFile)
            );
        }

        // pclose no espera la finalización del proceso hijo en Windows con 'start /B'
        @pclose(@popen($command, 'r'));
    }
}