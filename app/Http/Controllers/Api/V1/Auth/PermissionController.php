<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Permission\StorePermissionRequest;
use App\Http\Requests\Auth\Permission\UpdatePermissionRequest;
use App\Services\Auth\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la gestión de permisos.
 *
 * Utiliza identificadores enteros (ID) conforme al esquema de base de datos.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    /**
     * Obtiene el listado de permisos paginado.
     */
    #[OA\Get(
        path: '/api/v1/auth/permissions',
        summary: 'Obtener listado de permisos paginado',
        operationId: 'getPermissions',
        tags: ['Permisos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', description: 'Término de búsqueda por nombre o descripción', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de permisos recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar los permisos.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');

            $data = $this->permissionService->getAllPermissionsWithPagination($perPage, $page, $search);

            return $this->successResponse($data, 'Listado de permisos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene el catálogo de todos los permisos sin paginar.
     */
    #[OA\Get(
        path: '/api/v1/auth/permissions/list',
        summary: 'Obtener catálogo de permisos completo',
        operationId: 'getPermissionsList',
        tags: ['Permisos'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de permisos recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar el catálogo.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->permissionService->getAllPermissions();

            return $this->successResponse($data, 'Catálogo de permisos recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene el detalle de un permiso por su ID numérico.
     */
    #[OA\Get(
        path: '/api/v1/auth/permissions/{id}',
        summary: 'Obtener detalle de un permiso',
        operationId: 'getPermissionById',
        tags: ['Permisos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID numérico del permiso', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del permiso recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Permiso no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar el permiso.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $record = $this->permissionService->getPermissionById($id);
            if (! $record) {
                return $this->errorResponse('Permiso no encontrado.', 404);
            }

            return $this->successResponse($record, 'Detalle del permiso recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Crea un nuevo permiso.
     */
    #[OA\Post(
        path: '/api/v1/auth/permissions',
        summary: 'Crear un nuevo permiso',
        operationId: 'createPermission',
        tags: ['Permisos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'guard_name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'users.create'),
                    new OA\Property(property: 'guard_name', type: 'string', example: 'api'),
                    new OA\Property(property: 'description', type: 'string', example: 'Permite la creación de usuarios en el sistema'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Permiso creado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Error de validación o unicidad.'),
            new OA\Response(response: 500, description: 'Error interno al crear el permiso.'),
        ]
    )]
    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $record = $this->permissionService->createPermission($request->validated());

            return $this->successResponse($record, 'Permiso creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Actualiza un permiso existente por su ID numérico.
     */
    #[OA\Put(
        path: '/api/v1/auth/permissions/{id}',
        summary: 'Actualizar un permiso existente',
        operationId: 'updatePermission',
        tags: ['Permisos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del permiso a actualizar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'users.store'),
                    new OA\Property(property: 'guard_name', type: 'string', example: 'api'),
                    new OA\Property(property: 'description', type: 'string', example: 'Permite registrar y guardar nuevos usuarios'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permiso actualizado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Permiso no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el permiso.'),
        ]
    )]
    public function update(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->permissionService->getPermissionById($id);
            if (! $record) {
                return $this->errorResponse('Permiso no encontrado para actualizar.', 404);
            }
            $updated = $this->permissionService->updatePermission($id, $request->validated());

            return $this->successResponse($updated, 'Permiso actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Elimina un permiso por su ID numérico.
     */
    #[OA\Delete(
        path: '/api/v1/auth/permissions/{id}',
        summary: 'Eliminar un permiso existente',
        operationId: 'deletePermission',
        tags: ['Permisos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID numérico del permiso a eliminar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permiso eliminado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Permiso no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el permiso.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $record = $this->permissionService->getPermissionById($id);
            if (! $record) {
                return $this->errorResponse('Permiso no encontrado para eliminar.', 404);
            }
            $this->permissionService->deletePermission($id);

            return $this->successResponse(null, 'Permiso eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
