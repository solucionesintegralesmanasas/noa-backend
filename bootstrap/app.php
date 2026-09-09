<?php

use App\Exceptions\Handler;
use App\Http\Middleware\ChatRateLimiter;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetCompanyContext;
use App\Http\Middleware\WebCronMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            SetCompanyContext::class,
        ]);

        $middleware->append([
            WebCronMiddleware::class,
        ]);

        $middleware->alias([
            'chat.limiter' => ChatRateLimiter::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // =====================================================================
        // EXCEPCIONES QUE NO SE REPORTAN
        // =====================================================================
        $exceptions->dontReport([
            ModelNotFoundException::class,
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            NotFoundHttpException::class,
            TooManyRequestsHttpException::class,
        ]);
        // =====================================================================
        // RENDERIZADO - DELEGAR AL HANDLER PERSONALIZADO
        // =====================================================================
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(Handler::class)->renderForApi($request, $e);
            }
        });
    })
    ->create();
