<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador API para la gestión de notificaciones y alertas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 */
class NotificationsController extends Controller
{
    public function __construct(
        private readonly NotificationsService $notificationsService
    ) {}

    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'Consultar listado paginado de notificaciones',
        operationId: 'listNotifications',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['PENDIENTE', 'LEIDA'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $status = $request->query('status');
            $type = $request->query('type');

            $data = $this->notificationsService->getNotificationsWithPagination($perPage, $page, $search, $companyUuid, $status, $type);

            return $this->successResponse($data, 'Listado paginado de notificaciones recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/notifications/{uuid}/toggle-status',
        summary: 'Alternar el estado de lectura de una notificación',
        operationId: 'toggleNotificationStatus',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado alternado con éxito.'),
            new OA\Response(response: 404, description: 'Notificación no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->notificationsService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La notificación solicitada no existe.', 404);
            }

            $this->notificationsService->toggleStatus($uuid, 'status', [
                'PENDIENTE' => 'LEIDA',
                'LEIDA' => 'PENDIENTE',
            ]);

            return $this->successResponse($record->fresh(), 'Estado de la notificación alternado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/notifications/{uuid}/read',
        summary: 'Marcar una notificación como leída',
        operationId: 'markNotificationAsRead',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notificación marcada como leída.'),
            new OA\Response(response: 404, description: 'Notificación no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function markAsRead(string $uuid): JsonResponse
    {
        try {
            $record = $this->notificationsService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La notificación solicitada no existe.', 404);
            }

            $this->notificationsService->markAsRead($uuid);

            return $this->successResponse($record->fresh(), 'Notificación marcada como leída con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/notifications/read-all',
        summary: 'Marcar todas las notificaciones como leídas',
        operationId: 'markAllNotificationsAsRead',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Todas las notificaciones marcadas como leídas.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $count = $this->notificationsService->markAllAsRead($companyUuid);

            return $this->successResponse(['marked_count' => $count], 'Todas las notificaciones han sido marcadas como leídas.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/notifications/sync',
        summary: 'Sincronizar manualmente las alertas de notificaciones',
        operationId: 'syncNotifications',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notificaciones sincronizadas con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function sync(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $this->notificationsService->syncNotifications($companyUuid);

            return $this->successResponse(null, 'Notificaciones sincronizadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/notifications/{uuid}',
        summary: 'Eliminar una notificación por UUID',
        operationId: 'destroyNotification',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notificación eliminada con éxito.'),
            new OA\Response(response: 404, description: 'Notificación no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->notificationsService->findByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La notificación solicitada no existe.', 404);
            }

            $this->notificationsService->delete($uuid);

            return $this->successResponse(null, 'Notificación eliminada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/notifications/stream',
        summary: 'Transmisión en tiempo real de notificaciones (SSE)',
        operationId: 'streamNotifications',
        tags: ['Notificaciones'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito (Stream de Server-Sent Events).'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function stream(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request) {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $lastId = null;

            @set_time_limit(0);
            session_write_close();

            $maxExecution = 1800;
            $startTime = time();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                if ((time() - $startTime) > $maxExecution) {
                    echo "event: timeout\n";
                    echo 'data: {"message":"Conexión terminada por límite de tiempo"}'."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    break;
                }

                $notifications = $this->notificationsService->getLatestNotifications($companyUuid, 10);
                $currentId = md5(json_encode($notifications));

                if ($currentId !== $lastId) {
                    echo 'data: '.json_encode($notifications)."\n\n";
                    $lastId = $currentId;
                } else {
                    echo ": heartbeat\n\n";
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(5);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
