<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\InspectionItem\StoreInspectionItemRequest;
use App\Http\Requests\Catalogs\InspectionItem\UpdateInspectionItemRequest;
use App\Services\Catalogs\InspectionItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ItemInspeccion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class InspectionItemController extends Controller
{
    public function __construct(
        private readonly InspectionItemService $inspectionItemService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items',
        summary: 'Consultar listado paginado de ItemInspeccion',
        operationId: 'listInspectionItems',
        tags: ['ItemInspeccion'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
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
            $data = $this->inspectionItemService->getAllInspectionItemsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de ItemInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items/list',
        summary: 'Obtener catálogo de ItemInspeccion',
        operationId: 'getAllInspectionItems',
        tags: ['ItemInspeccion'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->inspectionItemService->getAllInspectionItems();

            return $this->successResponse($data, 'Catálogo de ItemInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/inspection-items/{uuid}',
        summary: 'Obtener detalle de ItemInspeccion por UUID',
        operationId: 'showInspectionItem',
        tags: ['ItemInspeccion'],
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
            $record = $this->inspectionItemService->getInspectionItemByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ItemInspeccion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ItemInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/inspection-items',
        summary: 'Crear nuevo registro de ItemInspeccion',
        operationId: 'storeInspectionItem',
        tags: ['ItemInspeccion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreInspectionItemRequest $request): JsonResponse
    {
        try {
            $record = $this->inspectionItemService->createInspectionItem($request->validated());

            return $this->successResponse($record, 'Registro de ItemInspeccion creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/inspection-items/{uuid}',
        summary: 'Actualizar registro de ItemInspeccion por UUID',
        operationId: 'updateInspectionItem',
        tags: ['ItemInspeccion'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateInspectionItemRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->inspectionItemService->getInspectionItemByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ItemInspeccion que desea actualizar no existe.', 404);
            }
            $updated = $this->inspectionItemService->updateInspectionItem($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ItemInspeccion actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/inspection-items/{uuid}',
        summary: 'Eliminar registro de ItemInspeccion por UUID',
        operationId: 'destroyInspectionItem',
        tags: ['ItemInspeccion'],
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
            $record = $this->inspectionItemService->getInspectionItemByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ItemInspeccion que desea eliminar no existe.', 404);
            }
            $this->inspectionItemService->deleteInspectionItem($uuid);

            return $this->successResponse(null, 'Registro de ItemInspeccion eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
