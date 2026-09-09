<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ModelHasRole\StoreModelHasRoleRequest;
use App\Services\Auth\ModelHasRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador para la asignación de roles a modelos.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.1
 *
 * @since    V 1.0.1
 *
 * @created  2025-05-31
 */
class ModelHasRoleController extends Controller
{
    public function __construct(
        private readonly ModelHasRoleService $modelHasRoleService
    ) {}

    #[OA\Get(
        path: '/api/v1/auth/model-has-roles',
        summary: 'Index ModelHasRole',
        operationId: 'indexModelHasRole',
        tags: ['ModelHasRole'],
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

            $data = $this->modelHasRoleService->getAllModelHasRolesWithPagination($perPage, $page, $search);

            return $this->successResponse($data, 'Listado de roles de modelo recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/model-has-roles',
        summary: 'Store ModelHasRole',
        operationId: 'storeModelHasRole',
        tags: ['ModelHasRole'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function store(StoreModelHasRoleRequest $request): JsonResponse
    {
        try {
            $record = $this->modelHasRoleService->createModelHasRole($request->validated());

            return $this->successResponse($record, 'Rol asignado al modelo con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Elimina una relación específica por clave compuesta.
     */
    #[OA\Delete(
        path: '/api/v1/auth/model-has-roles/{roleId}/{modelId}/{modelType}',
        summary: 'Destroy ModelHasRole',
        operationId: 'destroyModelHasRole',
        tags: ['ModelHasRole'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'roleId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'modelId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'modelType', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(int $roleId, int $modelId, string $modelType): JsonResponse
    {
        try {
            $record = $this->modelHasRoleService->getByCompositeKey($roleId, $modelId, $modelType);
            if (! $record) {
                return $this->errorResponse('Relación no encontrada.', 404);
            }
            $this->modelHasRoleService->deleteByCompositeKey($roleId, $modelId, $modelType);

            return $this->successResponse(null, 'Rol revocado del modelo con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
