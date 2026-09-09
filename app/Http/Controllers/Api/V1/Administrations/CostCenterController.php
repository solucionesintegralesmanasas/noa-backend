<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\CostCenter\StoreCostCenterRequest;
use App\Http\Requests\Administrations\CostCenter\UpdateCostCenterRequest;
use App\Services\Administrations\CostCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de centros de costo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-08
 */
class CostCenterController extends Controller
{
    public function __construct(
        private readonly CostCenterService $costCenterService
    ) {}

    #[OA\Get(
        path: '/api/v1/administration/cost-centers',
        summary: 'Consultar listado paginado de centros de costo',
        operationId: 'listCostCenters',
        tags: ['CentroCosto'],
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
            $data = $this->costCenterService->getAllCostCentersWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de centros de costo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/cost-centers/list',
        summary: 'Obtener catálogo de centros de costo',
        operationId: 'getAllCostCenters',
        tags: ['CentroCosto'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->costCenterService->getAllCostCenters();

            return $this->successResponse($data, 'Catálogo de centros de costo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/cost-centers/{uuid}',
        summary: 'Obtener detalle de centro de costo por UUID',
        operationId: 'showCostCenter',
        tags: ['CentroCosto'],
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
            $record = $this->costCenterService->getCostCenterByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El centro de costo solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de centro de costo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/cost-centers',
        summary: 'Crear nuevo centro de costo',
        operationId: 'storeCostCenter',
        tags: ['CentroCosto'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreCostCenterRequest $request): JsonResponse
    {
        try {
            $record = $this->costCenterService->createCostCenter($request->validated());

            return $this->successResponse($record, 'Centro de costo creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/cost-centers/{uuid}',
        summary: 'Actualizar centro de costo por UUID',
        operationId: 'updateCostCenter',
        tags: ['CentroCosto'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateCostCenterRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->costCenterService->getCostCenterByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El centro de costo que desea actualizar no existe.', 404);
            }
            $updated = $this->costCenterService->updateCostCenter($uuid, $request->validated());

            return $this->successResponse($updated, 'Centro de costo actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/cost-centers/{uuid}',
        summary: 'Eliminar centro de costo por UUID',
        operationId: 'destroyCostCenter',
        tags: ['CentroCosto'],
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
            $record = $this->costCenterService->getCostCenterByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El centro de costo que desea eliminar no existe.', 404);
            }
            $this->costCenterService->deleteCostCenter($uuid);

            return $this->successResponse(null, 'Centro de costo eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
