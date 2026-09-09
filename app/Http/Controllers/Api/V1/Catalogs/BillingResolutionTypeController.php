<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\BillingResolutionType\StoreBillingResolutionTypeRequest;
use App\Http\Requests\Catalogs\BillingResolutionType\UpdateBillingResolutionTypeRequest;
use App\Services\Catalogs\BillingResolutionTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de TipoResolucionFacturacion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class BillingResolutionTypeController extends Controller
{
    public function __construct(
        private readonly BillingResolutionTypeService $billingResolutionTypeService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/billing-resolution-types',
        summary: 'Consultar listado paginado de TipoResolucionFacturacion',
        operationId: 'listBillingResolutionTypes',
        tags: ['TipoResolucionFacturacion'],
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
            $data = $this->billingResolutionTypeService->getAllBillingResolutionTypesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de TipoResolucionFacturacion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/billing-resolution-types/list',
        summary: 'Obtener catálogo de TipoResolucionFacturacion',
        operationId: 'getAllBillingResolutionTypes',
        tags: ['TipoResolucionFacturacion'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->billingResolutionTypeService->getAllBillingResolutionTypes();

            return $this->successResponse($data, 'Catálogo de TipoResolucionFacturacion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/billing-resolution-types/{uuid}',
        summary: 'Obtener detalle de TipoResolucionFacturacion por UUID',
        operationId: 'showBillingResolutionType',
        tags: ['TipoResolucionFacturacion'],
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
            $record = $this->billingResolutionTypeService->getBillingResolutionTypeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoResolucionFacturacion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de TipoResolucionFacturacion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/billing-resolution-types',
        summary: 'Crear nuevo registro de TipoResolucionFacturacion',
        operationId: 'storeBillingResolutionType',
        tags: ['TipoResolucionFacturacion'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreBillingResolutionTypeRequest $request): JsonResponse
    {
        try {
            $record = $this->billingResolutionTypeService->createBillingResolutionType($request->validated());

            return $this->successResponse($record, 'Registro de TipoResolucionFacturacion creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/billing-resolution-types/{uuid}',
        summary: 'Actualizar registro de TipoResolucionFacturacion por UUID',
        operationId: 'updateBillingResolutionType',
        tags: ['TipoResolucionFacturacion'],
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
    public function update(UpdateBillingResolutionTypeRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->billingResolutionTypeService->getBillingResolutionTypeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoResolucionFacturacion que desea actualizar no existe.', 404);
            }
            $updated = $this->billingResolutionTypeService->updateBillingResolutionType($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de TipoResolucionFacturacion actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/billing-resolution-types/{uuid}',
        summary: 'Eliminar registro de TipoResolucionFacturacion por UUID',
        operationId: 'destroyBillingResolutionType',
        tags: ['TipoResolucionFacturacion'],
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
            $record = $this->billingResolutionTypeService->getBillingResolutionTypeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoResolucionFacturacion que desea eliminar no existe.', 404);
            }
            $this->billingResolutionTypeService->deleteBillingResolutionType($uuid);

            return $this->successResponse(null, 'Registro de TipoResolucionFacturacion eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
