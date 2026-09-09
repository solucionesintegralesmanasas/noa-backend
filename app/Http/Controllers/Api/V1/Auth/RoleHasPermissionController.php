<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RoleHasPermission\StoreRoleHasPermissionRequest;
use App\Services\Auth\RoleHasPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la asociación de roles y permisos.
 *
 * Gestiona la tabla pivote mediante claves compuestas.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class RoleHasPermissionController extends Controller
{
    public function __construct(
        private readonly RoleHasPermissionService $roleHasPermissionService
    ) {}

    #[OA\Get(
        path: '/api/v1/auth/role-has-permissions',
        summary: 'Index RoleHasPermission',
        operationId: 'indexRoleHasPermission',
        tags: ['RoleHasPermission'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');

            $data = $this->roleHasPermissionService->getAllRoleHasPermissionsWithPagination($perPage, $page, $search);

            return $this->successResponse($data, 'Listado de permisos de rol recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/role-has-permissions',
        summary: 'Store RoleHasPermission',
        operationId: 'storeRoleHasPermission',
        tags: ['RoleHasPermission'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function store(StoreRoleHasPermissionRequest $request): JsonResponse
    {
        try {
            $record = $this->roleHasPermissionService->createRoleHasPermission($request->validated());

            return $this->successResponse($record, 'Permiso asignado al rol con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Elimina una relación específica por clave compuesta.
     */
    #[OA\Delete(
        path: '/api/v1/auth/role-has-permissions/{permissionId}/{roleId}',
        summary: 'Destroy RoleHasPermission',
        operationId: 'destroyRoleHasPermission',
        tags: ['RoleHasPermission'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(int $permissionId, int $roleId): JsonResponse
    {
        try {
            $record = $this->roleHasPermissionService->getByCompositeKey($permissionId, $roleId);
            if (! $record) {
                return $this->errorResponse('Relación no encontrada.', 404);
            }
            $this->roleHasPermissionService->deleteByCompositeKey($permissionId, $roleId);

            return $this->successResponse(null, 'Permiso revocado del rol con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
