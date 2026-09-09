<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\User\StoreUserRequest;
use App\Http\Requests\Auth\User\UpdateUserRequest;
use App\Services\Auth\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la gestión de usuarios.
 *
 * Utiliza UUID como identificador y soporta filtrado multi-tenant.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Obtiene el listado de usuarios paginado.
     */
    #[OA\Get(
        path: '/api/v1/auth/users',
        summary: 'Obtener listado de usuarios paginado',
        operationId: 'getUsers',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', description: 'Término de búsqueda', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', description: 'UUID de la empresa para filtrar', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de usuarios recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar los usuarios.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');

            $data = $this->userService->getAllUsersWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de usuarios recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene todo el catálogo de usuarios sin paginación.
     */
    #[OA\Get(
        path: '/api/v1/auth/users/list',
        summary: 'Obtener catálogo de usuarios completo',
        operationId: 'getUsersList',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de usuarios recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar el catálogo.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->userService->getAllUsers();

            return $this->successResponse($data, 'Catálogo de usuarios recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene el detalle de un usuario específico por su UUID.
     */
    #[OA\Get(
        path: '/api/v1/auth/users/{uuid}',
        summary: 'Obtener detalle de un usuario',
        operationId: 'getUserByUuid',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'UUID del usuario', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del usuario recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar el usuario.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->userService->getUserByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Usuario no encontrado.', 404);
            }

            return $this->successResponse($record, 'Detalle del usuario recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Crea un nuevo usuario.
     */
    #[OA\Post(
        path: '/api/v1/auth/users',
        summary: 'Crear un nuevo usuario',
        operationId: 'createUser',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan.perez@empresa.com'),
                    new OA\Property(property: 'user_name', type: 'string', example: 'jperez'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                    new OA\Property(property: 'third_party_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174001'),
                    new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Error de validación en los datos enviados.'),
            new OA\Response(response: 500, description: 'Error interno al crear el usuario.'),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $record = $this->userService->createUser($request->validated());

            return $this->successResponse($record, 'Usuario creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Actualiza un usuario existente por su UUID.
     */
    #[OA\Put(
        path: '/api/v1/auth/users/{uuid}',
        summary: 'Actualizar un usuario existente',
        operationId: 'updateUser',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'UUID del usuario a actualizar', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez Editado'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan.perez.new@empresa.com'),
                    new OA\Property(property: 'user_name', type: 'string', example: 'jperezedit'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newsecret123'),
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                    new OA\Property(property: 'third_party_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174001'),
                    new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación en los datos enviados.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el usuario.'),
        ]
    )]
    public function update(UpdateUserRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->userService->getUserByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Usuario no encontrado para actualizar.', 404);
            }
            $updated = $this->userService->updateUser($uuid, $request->validated());

            return $this->successResponse($updated, 'Usuario actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Elimina un usuario por su UUID.
     */
    #[OA\Delete(
        path: '/api/v1/auth/users/{uuid}',
        summary: 'Eliminar un usuario',
        operationId: 'deleteUser',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'UUID del usuario a eliminar', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario eliminado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el usuario.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->userService->getUserByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Usuario no encontrado para eliminar.', 404);
            }
            $this->userService->deleteUser($uuid);

            return $this->successResponse(null, 'Usuario eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Alterna el estado activo/inactivo del usuario.
     */
    #[OA\Patch(
        path: '/api/v1/auth/users/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo del usuario',
        operationId: 'toggleUserStatus',
        tags: ['Usuarios'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', description: 'UUID del usuario', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado del usuario actualizado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al alternar el estado.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->userService->getUserByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Usuario no encontrado.', 404);
            }
            $result = $this->userService->toggleUserStatus($uuid);

            return $this->successResponse(['active' => $result], 'Estado del usuario actualizado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
