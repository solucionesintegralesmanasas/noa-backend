<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege los endpoints de Web Cron (hosting compartido sin cron real).
 *
 * El llamador debe enviar el secreto de CRON_SECRET en la cabecera `X-Cron-Token`
 * o en `?token=` (los servicios de cron web solo permiten URL). Falla cerrado:
 * si CRON_SECRET no está configurado, nadie puede ejecutar los procesos.
 */
class VerifyCronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('services.cron.secret');
        $recibido = (string) ($request->header('X-Cron-Token') ?? $request->query('token', ''));

        if ($esperado === '' || ! hash_equals($esperado, $recibido)) {
            if ($esperado === '') {
                Log::warning('Cron web rechazado: CRON_SECRET no está configurado.');
            }

            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        return $next($request);
    }
}
