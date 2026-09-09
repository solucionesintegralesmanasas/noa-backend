<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\DianParameter\StoreDianParameterRequest;
use App\Http\Requests\Catalogs\DianParameter\UpdateDianParameterRequest;
use App\Services\Catalogs\DianParameterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ParametroDian.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class DianParameterController extends Controller
{
    public function __construct(
        private readonly DianParameterService $dianParameterService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/dian-parameters',
        summary: 'Consultar listado paginado de ParametroDian',
        operationId: 'listDianParameters',
        tags: ['ParametroDian'],
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
            $data = $this->dianParameterService->getAllDianParametersWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de ParametroDian recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/dian-parameters/list',
        summary: 'Obtener catálogo de ParametroDian',
        operationId: 'getAllDianParameters',
        tags: ['ParametroDian'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->dianParameterService->getAllDianParameters();

            return $this->successResponse($data, 'Catálogo de ParametroDian recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/dian-parameters/{uuid}',
        summary: 'Obtener detalle de ParametroDian por UUID',
        operationId: 'showDianParameter',
        tags: ['ParametroDian'],
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
            $record = $this->dianParameterService->getDianParameterByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ParametroDian solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ParametroDian recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/dian-parameters',
        summary: 'Crear nuevo registro de ParametroDian',
        operationId: 'storeDianParameter',
        tags: ['ParametroDian'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreDianParameterRequest $request): JsonResponse
    {
        try {
            $record = $this->dianParameterService->createDianParameter($request->validated());

            return $this->successResponse($record, 'Registro de ParametroDian creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/dian-parameters/{uuid}',
        summary: 'Actualizar registro de ParametroDian por UUID',
        operationId: 'updateDianParameter',
        tags: ['ParametroDian'],
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
    public function update(UpdateDianParameterRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->dianParameterService->getDianParameterByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ParametroDian que desea actualizar no existe.', 404);
            }
            $updated = $this->dianParameterService->updateDianParameter($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ParametroDian actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/dian-parameters/{uuid}',
        summary: 'Eliminar registro de ParametroDian por UUID',
        operationId: 'destroyDianParameter',
        tags: ['ParametroDian'],
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
            $record = $this->dianParameterService->getDianParameterByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ParametroDian que desea eliminar no existe.', 404);
            }
            $this->dianParameterService->deleteDianParameter($uuid);

            return $this->successResponse(null, 'Registro de ParametroDian eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
