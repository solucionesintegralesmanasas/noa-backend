<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ModelHasPermission\StoreModelHasPermissionRequest;
use App\Services\Auth\ModelHasPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la asignación de permisos a modelos.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class ModelHasPermissionController extends Controller
{
    public function __construct(
        private readonly ModelHasPermissionService $modelHasPermissionService
    ) {}

    #[OA\Get(
        path: '/api/v1/auth/model-has-permissions',
        summary: 'Index ModelHasPermission',
        operationId: 'indexModelHasPermission',
        tags: ['ModelHasPermission'],
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

            $data = $this->modelHasPermissionService->getAllModelHasPermissionsWithPagination($perPage, $page, $search);

            return $this->successResponse($data, 'Listado de permisos de modelo recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/model-has-permissions',
        summary: 'Store ModelHasPermission',
        operationId: 'storeModelHasPermission',
        tags: ['ModelHasPermission'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function store(StoreModelHasPermissionRequest $request): JsonResponse
    {
        try {
            $record = $this->modelHasPermissionService->createModelHasPermission($request->validated());

            return $this->successResponse($record, 'Permiso asignado al modelo con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Elimina una relación específica por clave compuesta.
     */
    #[OA\Delete(
        path: '/api/v1/auth/model-has-permissions/{permissionId}/{modelId}/{modelType}',
        summary: 'Destroy ModelHasPermission',
        operationId: 'destroyModelHasPermission',
        tags: ['ModelHasPermission'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'modelId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'modelType', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(int $permissionId, int $modelId, string $modelType): JsonResponse
    {
        try {
            $record = $this->modelHasPermissionService->getByCompositeKey($permissionId, $modelId, $modelType);
            if (! $record) {
                return $this->errorResponse('Relación no encontrada.', 404);
            }
            $this->modelHasPermissionService->deleteByCompositeKey($permissionId, $modelId, $modelType);

            return $this->successResponse(null, 'Permiso revocado del modelo con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
