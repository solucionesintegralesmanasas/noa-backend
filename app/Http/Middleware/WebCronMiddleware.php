<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $php = $this->phpBinary();

        if ($php === null) {
            Log::error('WebCron: no se encontró un binario PHP CLI para lanzar webcron:run. Configure WEBCRON_PHP_BINARY.');

            return;
        }

        $artisan = base_path('artisan');
        $logFile = storage_path('logs/webcron.log');

        if (PHP_OS_FAMILY === 'Windows') {
            // 'start "" /B' ejecuta el comando en segundo plano sin abrir una ventana
            $command = sprintf(
                'start "" /B %s %s webcron:run --no-ansi > %s 2>&1',
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($logFile)
            );
        } else {
            $command = sprintf(
                '%s %s webcron:run --no-ansi > %s 2>&1 &',
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($logFile)
            );
        }

        // pclose no espera la finalización del proceso hijo en Windows con 'start /B'
        @pclose(@popen($command, 'r'));
    }

    /**
     * Resuelve un binario PHP de línea de comandos.
     *
     * `PHP_BINARY` no sirve cuando la petición la atiende Apache/mod_php o
     * php-fpm: apunta a `httpd.exe` o `php-fpm`, y ejecutarlos con argumentos
     * de Artisan no corre el comando (en Windows incluso escribe errores de
     * Apache en el log del webcron). Por eso se busca el PHP CLI real.
     */
    private function phpBinary(): ?string
    {
        $configured = config('services.webcron.php_binary');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        if (PHP_SAPI === 'cli' && is_file(PHP_BINARY)) {
            return PHP_BINARY;
        }

        $executable = 'php'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        $candidates = [
            rtrim(PHP_BINDIR, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$executable,
            // XAMPP: PHP_BINARY es apache/bin/httpd.exe; el CLI está en ../php/php.exe.
            dirname(PHP_BINARY, 3).DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.$executable,
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.$executable,
            '/usr/bin/'.$executable,
            '/usr/local/bin/'.$executable,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Último recurso: solo si el binario parece PHP; nunca httpd/php-fpm.
        if (is_file(PHP_BINARY) && stripos(basename(PHP_BINARY), 'php') === 0) {
            return PHP_BINARY;
        }

        return null;
    }
}