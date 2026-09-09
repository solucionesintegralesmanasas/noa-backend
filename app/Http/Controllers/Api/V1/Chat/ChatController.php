<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateSessionRequest;
use App\Http\Requests\Chat\FeedbackRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para el módulo de chat asistente Next Assistant.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-15
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly AssistantService $assistantService
    ) {}

    #[OA\Get(
        path: '/api/v1/chat/sessions',
        summary: 'Listar sesiones activas del usuario autenticado',
        operationId: 'listChatSessions',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ACTIVA', 'CERRADA'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de sesiones obtenido con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $status = $request->query('status', 'ACTIVA');

            $sessions = $this->assistantService->search(
                filters: [
                    'user_uuid' => $request->user()->uuid,
                    'status' => $status,
                ],
                columns: ['id', 'uuid', 'title', 'module', 'status', 'updated_at', 'created_at'],
                perPage: $perPage,
            );

            return $this->successResponse($sessions, 'Sesiones obtenidas correctamente');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/chat/sessions',
        summary: 'Crear una nueva sesión de conversación',
        operationId: 'createChatSession',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', nullable: true),
                    new OA\Property(property: 'module', type: 'string', nullable: true, enum: ['transporte', 'facturacion', 'inventario', 'general']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sesión creada exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
        ]
    )]
    public function store(CreateSessionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyUuid = $request->attributes->get('current_company_uuid');

            $session = $this->assistantService->create([
                'user_uuid' => $user->uuid,
                'company_uuid' => $companyUuid,
                'title' => $request->input('title', 'Nueva Conversación'),
                'module' => $request->input('module', 'general'),
                'status' => 'ACTIVA',
            ]);

            return $this->createdResponse($session->fresh(), 'Sesión creada exitosamente');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/chat/sessions/{uuid}',
        summary: 'Obtener detalle de una sesión con su historial de mensajes',
        operationId: 'showChatSession',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sesión encontrada.'),
            new OA\Response(response: 404, description: 'Sesión no encontrada.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $session = $this->assistantService->getSessionWithMessages($uuid);

            if (! $session || $session->user_uuid !== request()->user()->uuid) {
                return $this->errorResponse('Sesión no encontrada.', 404);
            }

            return $this->successResponse($session, 'Sesión obtenida correctamente');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/chat/sessions/{uuid}/messages',
        summary: 'Enviar un mensaje y procesar la respuesta del asistente',
        operationId: 'sendChatMessage',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', description: 'Contenido del mensaje'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Mensaje procesado correctamente.'),
            new OA\Response(response: 404, description: 'Sesión no encontrada.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
        ]
    )]
    public function send(SendMessageRequest $request, string $uuid): JsonResponse
    {
        try {
            $session = $this->assistantService->findByUuid($uuid);

            if (! $session || $session->user_uuid !== $request->user()->uuid) {
                return $this->errorResponse('Sesión no válida o no pertenece al usuario.', 404);
            }

            $responseMessage = $this->assistantService->processMessage(
                $session,
                $request->user(),
                $request->input('content')
            );

            return $this->createdResponse([
                'message' => $responseMessage,
                'session' => $session->fresh(),
            ], 'Mensaje procesado correctamente');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/chat/sessions/{uuid}',
        summary: 'Cerrar (archivar) una sesión de chat',
        operationId: 'closeChatSession',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada correctamente.'),
            new OA\Response(response: 404, description: 'Sesión no encontrada.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $session = $this->assistantService->findByUuid($uuid);

            if (! $session || $session->user_uuid !== request()->user()->uuid) {
                return $this->errorResponse('Sesión no encontrada.', 404);
            }

            $this->assistantService->update($uuid, ['status' => 'CERRADA']);

            return $this->successResponse(
                $session->fresh(),
                'Sesión cerrada correctamente'
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/chat/messages/{uuid}/feedback',
        summary: 'Registrar feedback (útil/no útil) sobre un mensaje del asistente',
        operationId: 'rateChatMessage',
        tags: ['Chat'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['feedback'],
                properties: [
                    new OA\Property(property: 'feedback', type: 'integer', enum: [-1, 1], description: '1 = útil, -1 = no útil'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Feedback registrado exitosamente.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Mensaje no encontrado.'),
        ]
    )]
    public function rateFeedback(string $uuid, FeedbackRequest $request): JsonResponse
    {
        try {
            $message = $this->assistantService->rateMessage(
                $uuid,
                (int) $request->input('feedback'),
                $request->user()
            );

            return $this->successResponse($message, 'Feedback registrado exitosamente');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
