<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Role\StoreRoleRequest;
use App\Http\Requests\Auth\Role\UpdateRoleRequest;
use App\Services\Auth\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la gestión de roles.
 *
 * Utiliza identificadores enteros (ID) y soporta filtrado por empresa.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    /**
     * Obtiene el listado de roles paginado.
     */
    #[OA\Get(
        path: '/api/v1/auth/roles',
        summary: 'Obtener listado de roles paginado',
        operationId: 'getRoles',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', description: 'Término de búsqueda por nombre', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', description: 'UUID de la empresa para filtrar', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de roles recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar los roles.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');

            $data = $this->roleService->getAllRolesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de roles recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene el catálogo de todos los roles sin paginar.
     */
    #[OA\Get(
        path: '/api/v1/auth/roles/list',
        summary: 'Obtener catálogo de roles completo',
        operationId: 'getRolesList',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de roles recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar el catálogo.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->roleService->getAllRoles();

            return $this->successResponse($data, 'Catálogo de roles recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Obtiene el detalle de un rol por su ID numérico.
     */
    #[OA\Get(
        path: '/api/v1/auth/roles/{id}',
        summary: 'Obtener detalle de un rol',
        operationId: 'getRoleById',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID numérico del rol', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del rol recuperado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Rol no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al recuperar el rol.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $record = $this->roleService->getRoleById($id);
            if (! $record) {
                return $this->errorResponse('Rol no encontrado.', 404);
            }

            return $this->successResponse($record, 'Detalle del rol recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Crea un nuevo rol.
     */
    #[OA\Post(
        path: '/api/v1/auth/roles',
        summary: 'Crear un nuevo rol',
        operationId: 'createRole',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'guard_name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Administrador'),
                    new OA\Property(property: 'guard_name', type: 'string', example: 'api'),
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Rol creado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 422, description: 'Error de validación o unicidad.'),
            new OA\Response(response: 500, description: 'Error interno al crear el rol.'),
        ]
    )]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $record = $this->roleService->createRole($request->validated());

            return $this->successResponse($record, 'Rol creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Actualiza un rol existente por su ID numérico.
     */
    #[OA\Put(
        path: '/api/v1/auth/roles/{id}',
        summary: 'Actualizar un rol existente',
        operationId: 'updateRole',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del rol a actualizar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Super Administrador'),
                    new OA\Property(property: 'guard_name', type: 'string', example: 'api'),
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rol actualizado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Rol no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno al actualizar el rol.'),
        ]
    )]
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->roleService->getRoleById($id);
            if (! $record) {
                return $this->errorResponse('Rol no encontrado para actualizar.', 404);
            }
            $updated = $this->roleService->updateRole($id, $request->validated());

            return $this->successResponse($updated, 'Rol actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Elimina un rol por su ID numérico.
     */
    #[OA\Delete(
        path: '/api/v1/auth/roles/{id}',
        summary: 'Eliminar un rol existente',
        operationId: 'deleteRole',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID numérico del rol a eliminar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol eliminado con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 404, description: 'Rol no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno al eliminar el rol.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $record = $this->roleService->getRoleById($id);
            if (! $record) {
                return $this->errorResponse('Rol no encontrado para eliminar.', 404);
            }
            $this->roleService->deleteRole($id);

            return $this->successResponse(null, 'Rol eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
