<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: ForceJsonResponse
 *
 * Fuerza el header Accept: application/json en todas las peticiones
 * que entren por el prefijo /api/.
 *
 * Esto garantiza que:
 * 1. El Handler detecte correctamente la petición como API ($request->expectsJson() = true).
 * 2. Laravel nunca devuelva una redirección o vista HTML ante errores de autenticación.
 * 3. Los clientes que olviden enviar Accept: application/json reciban JSON de todas formas.
 *
 * REGISTRO: Añadir en bootstrap/app.php dentro de ->withMiddleware():
 *
 *   $middleware->api(prepend: [
 *       \App\Http\Middleware\ForceJsonResponse::class,
 *   ]);
 *
 * @author  Darwin Montes Lopez
 *
 * @version 1.0.0
 */
class ForceJsonResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Fuerza el header Accept a application/json para todas las peticiones API.
        // Esto hace que $request->expectsJson() devuelva true en el Handler.
        $request->headers->set('Accept', 'application/json');

        // Si viene un token en la query (útil para EventSource/SSE) y no hay cabecera de Autorización
        if ($request->has('token') && ! $request->headers->has('Authorization')) {
            $request->headers->set('Authorization', 'Bearer '.$request->query('token'));
        }

        return $next($request);
    }
}
