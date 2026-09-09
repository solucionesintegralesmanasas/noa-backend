<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\CapacityInventory\StoreCapacityInventoryRequest;
use App\Http\Requests\Procedure\CapacityInventory\UpdateCapacityInventoryRequest;
use App\Services\Procedure\CapacityInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de InventarioCapacidad.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class CapacityInventoryController extends Controller
{
    public function __construct(
        private readonly CapacityInventoryService $capacityInventoryService
    ) {}

    #[OA\Get(
        path: '/api/v1/procedure/capacity-inventories',
        summary: 'Consultar listado paginado de InventarioCapacidad',
        operationId: 'listCapacityInventories',
        tags: ['InventarioCapacidad'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
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
            $companyUuid = $request->query('company_uuid');
            $data = $this->capacityInventoryService->getAllCapacityInventoriesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de InventarioCapacidad recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/capacity-inventories/list',
        summary: 'Obtener catálogo de InventarioCapacidad',
        operationId: 'getAllCapacityInventories',
        tags: ['InventarioCapacidad'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->capacityInventoryService->getAllCapacityInventories();

            return $this->successResponse($data, 'Catálogo de InventarioCapacidad recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/capacity-inventories/{uuid}',
        summary: 'Obtener detalle de InventarioCapacidad por UUID',
        operationId: 'showCapacityInventory',
        tags: ['InventarioCapacidad'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro encontrado.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->capacityInventoryService->getCapacityInventoryByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de InventarioCapacidad solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de InventarioCapacidad recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/procedure/capacity-inventories',
        summary: 'Crear nuevo registro de InventarioCapacidad',
        operationId: 'storeCapacityInventory',
        tags: ['InventarioCapacidad'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreCapacityInventoryRequest $request): JsonResponse
    {
        try {
            $record = $this->capacityInventoryService->createCapacityInventory($request->validated());

            return $this->successResponse($record, 'Registro de InventarioCapacidad creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/procedure/capacity-inventories/{uuid}',
        summary: 'Actualizar registro de InventarioCapacidad por UUID',
        operationId: 'updateCapacityInventory',
        tags: ['InventarioCapacidad'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateCapacityInventoryRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->capacityInventoryService->getCapacityInventoryByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de InventarioCapacidad que desea actualizar no existe.', 404);
            }
            $updated = $this->capacityInventoryService->updateCapacityInventory($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de InventarioCapacidad actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/procedure/capacity-inventories/{uuid}',
        summary: 'Eliminar registro de InventarioCapacidad por UUID',
        operationId: 'destroyCapacityInventory',
        tags: ['InventarioCapacidad'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->capacityInventoryService->getCapacityInventoryByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de InventarioCapacidad que desea eliminar no existe.', 404);
            }
            $this->capacityInventoryService->deleteCapacityInventory($uuid);

            return $this->successResponse(null, 'Registro de InventarioCapacidad eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
