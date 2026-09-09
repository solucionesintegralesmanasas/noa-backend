<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\Withholding\StoreWithholdingRequest;
use App\Http\Requests\Catalogs\Withholding\UpdateWithholdingRequest;
use App\Services\Catalogs\WithholdingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Retencion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class WithholdingController extends Controller
{
    public function __construct(
        private readonly WithholdingService $withholdingService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/withholdings',
        summary: 'Consultar listado paginado de Retencion',
        operationId: 'listWithholdings',
        tags: ['Retencion'],
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
            $data = $this->withholdingService->getAllWithholdingsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Retencion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/withholdings/list',
        summary: 'Obtener catálogo de Retencion',
        operationId: 'getAllWithholdings',
        tags: ['Retencion'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->withholdingService->getAllWithholdings();

            return $this->successResponse($data, 'Catálogo de Retencion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/withholdings/{uuid}',
        summary: 'Obtener detalle de Retencion por UUID',
        operationId: 'showWithholding',
        tags: ['Retencion'],
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
            $record = $this->withholdingService->getWithholdingByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Retencion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Retencion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/withholdings',
        summary: 'Crear nuevo registro de Retencion',
        operationId: 'storeWithholding',
        tags: ['Retencion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreWithholdingRequest $request): JsonResponse
    {
        try {
            $record = $this->withholdingService->createWithholding($request->validated());

            return $this->successResponse($record, 'Registro de Retencion creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/withholdings/{uuid}',
        summary: 'Actualizar registro de Retencion por UUID',
        operationId: 'updateWithholding',
        tags: ['Retencion'],
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
    public function update(UpdateWithholdingRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->withholdingService->getWithholdingByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Retencion que desea actualizar no existe.', 404);
            }
            $updated = $this->withholdingService->updateWithholding($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Retencion actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/withholdings/{uuid}',
        summary: 'Eliminar registro de Retencion por UUID',
        operationId: 'destroyWithholding',
        tags: ['Retencion'],
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
            $record = $this->withholdingService->getWithholdingByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Retencion que desea eliminar no existe.', 404);
            }
            $this->withholdingService->deleteWithholding($uuid);

            return $this->successResponse(null, 'Registro de Retencion eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
