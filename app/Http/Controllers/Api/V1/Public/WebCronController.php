<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\WebCronService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para endpoints de Web Cron público.
 *
 * Proporciona endpoints para el estado, configuración y ejecución manual del Web Cron
 * en entornos de hosting compartido sin acceso a cron.
 *
 * @author Claude Code
 *
 * @version 1.0.0
 *
 * @since 2026-11-11
 */
class WebCronController extends Controller
{
    public function __construct(
        private readonly WebCronService $webCronService
    ) {}

    #[OA\Get(
        path: '/api/v1/public/cron/status',
        summary: 'Obtener estado actual del Web Cron',
        operationId: 'getWebCronStatus',
        tags: ['Web Cron'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Estado del Web Cron recuperado con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function status(): JsonResponse
    {
        try {
            $status = $this->webCronService->getStatus();

            return $this->successResponse($status, 'Estado del Web Cron recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/public/cron/config',
        summary: 'Obtener configuración actual del Web Cron',
        operationId: 'getWebCronConfig',
        tags: ['Web Cron'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Configuración del Web Cron recuperada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function config(): JsonResponse
    {
        try {
            $config = $this->webCronService->getConfig();

            return $this->successResponse($config, 'Configuración del Web Cron recuperada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/public/cron/run',
        summary: 'Ejecutar tareas del Web Cron manualmente',
        operationId: 'runWebCronManually',
        tags: ['Web Cron'],
        security: [],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'force',
                        type: 'boolean',
                        default: false,
                        description: 'Forzar ejecución aunque ya esté corriendo'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Web Cron ejecutado con éxito.'),
            new OA\Response(response: 409, description: 'Web Cron ya está en ejecución.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function run(Request $request): JsonResponse
    {
        try {
            $force = (bool) $request->input('force', false);

            $result = $this->webCronService->run($force);

            return $this->successResponse($result, 'Tareas del Web Cron ejecutadas con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/public/cron/stop',
        summary: 'Detener ejecución del Web Cron',
        operationId: 'stopWebCron',
        tags: ['Web Cron'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Web Cron detenido con éxito.'),
            new OA\Response(response: 404, description: 'No hay proceso activo del Web Cron.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function stop(): JsonResponse
    {
        try {
            $result = $this->webCronService->stop();

            return $this->successResponse($result, 'Web Cron detenido con éxito.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}