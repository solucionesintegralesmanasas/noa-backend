<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Middleware para limitar la frecuencia de uso del chat asistente.
 *
 * Aplica dos niveles de rate limiting:
 * - 5 solicitudes por minuto (por usuario)
 * - 20 solicitudes por hora (por usuario)
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-15
 */
class ChatRateLimiter
{
    /** @var int Máximo de solicitudes por minuto */
    private const MAX_PER_MINUTE = 30;

    /** @var int Máximo de solicitudes por hora */
    private const MAX_PER_HOUR = 200;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $key = 'chat:'.$user->id;

        if (RateLimiter::tooManyAttempts($key.':minute', self::MAX_PER_MINUTE)) {
            throw new TooManyRequestsHttpException(
                null,
                'Demasiadas solicitudes por minuto. Intente de nuevo en unos segundos.'
            );
        }

        if (RateLimiter::tooManyAttempts($key.':hour', self::MAX_PER_HOUR)) {
            throw new TooManyRequestsHttpException(
                null,
                'Límite horario alcanzado. Intente de nuevo más tarde.'
            );
        }

        RateLimiter::hit($key.':minute', 60);
        RateLimiter::hit($key.':hour', 3600);

        return $next($request);
    }
}
