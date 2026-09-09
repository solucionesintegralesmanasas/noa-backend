<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\PucCommercial\StorePucCommercialRequest;
use App\Http\Requests\Catalogs\PucCommercial\UpdatePucCommercialRequest;
use App\Services\Catalogs\PucCommercialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de PucComercial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class PucCommercialController extends Controller
{
    public function __construct(
        private readonly PucCommercialService $pucCommercialService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/puc-commercials',
        summary: 'Consultar listado paginado de PucComercial',
        operationId: 'listPucCommercials',
        tags: ['PucComercial'],
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
            $data = $this->pucCommercialService->getAllPucCommercialsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de PucComercial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/puc-commercials/list',
        summary: 'Obtener catálogo de PucComercial',
        operationId: 'getAllPucCommercials',
        tags: ['PucComercial'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->pucCommercialService->getAllPucCommercials();

            return $this->successResponse($data, 'Catálogo de PucComercial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/puc-commercials/{uuid}',
        summary: 'Obtener detalle de PucComercial por UUID',
        operationId: 'showPucCommercial',
        tags: ['PucComercial'],
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
            $record = $this->pucCommercialService->getPucCommercialByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de PucComercial solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de PucComercial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/puc-commercials',
        summary: 'Crear nuevo registro de PucComercial',
        operationId: 'storePucCommercial',
        tags: ['PucComercial'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StorePucCommercialRequest $request): JsonResponse
    {
        try {
            $record = $this->pucCommercialService->createPucCommercial($request->validated());

            return $this->successResponse($record, 'Registro de PucComercial creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/puc-commercials/{uuid}',
        summary: 'Actualizar registro de PucComercial por UUID',
        operationId: 'updatePucCommercial',
        tags: ['PucComercial'],
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
    public function update(UpdatePucCommercialRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->pucCommercialService->getPucCommercialByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de PucComercial que desea actualizar no existe.', 404);
            }
            $updated = $this->pucCommercialService->updatePucCommercial($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de PucComercial actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/puc-commercials/{uuid}',
        summary: 'Eliminar registro de PucComercial por UUID',
        operationId: 'destroyPucCommercial',
        tags: ['PucComercial'],
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
            $record = $this->pucCommercialService->getPucCommercialByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de PucComercial que desea eliminar no existe.', 404);
            }
            $this->pucCommercialService->deletePucCommercial($uuid);

            return $this->successResponse(null, 'Registro de PucComercial eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
