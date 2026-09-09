<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\TaxResponsibility\StoreTaxResponsibilityRequest;
use App\Http\Requests\Catalogs\TaxResponsibility\UpdateTaxResponsibilityRequest;
use App\Services\Catalogs\TaxResponsibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ResponsabilidadTributaria.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TaxResponsibilityController extends Controller
{
    public function __construct(
        private readonly TaxResponsibilityService $taxResponsibilityService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/tax-responsibilities',
        summary: 'Consultar listado paginado de ResponsabilidadTributaria',
        operationId: 'listTaxResponsibilities',
        tags: ['ResponsabilidadTributaria'],
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
            $data = $this->taxResponsibilityService->getAllTaxResponsibilitiesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de ResponsabilidadTributaria recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/tax-responsibilities/list',
        summary: 'Obtener catálogo de ResponsabilidadTributaria',
        operationId: 'getAllTaxResponsibilities',
        tags: ['ResponsabilidadTributaria'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->taxResponsibilityService->getAllTaxResponsibilities();

            return $this->successResponse($data, 'Catálogo de ResponsabilidadTributaria recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/tax-responsibilities/{uuid}',
        summary: 'Obtener detalle de ResponsabilidadTributaria por UUID',
        operationId: 'showTaxResponsibility',
        tags: ['ResponsabilidadTributaria'],
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
            $record = $this->taxResponsibilityService->getTaxResponsibilityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ResponsabilidadTributaria solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ResponsabilidadTributaria recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/tax-responsibilities',
        summary: 'Crear nuevo registro de ResponsabilidadTributaria',
        operationId: 'storeTaxResponsibility',
        tags: ['ResponsabilidadTributaria'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreTaxResponsibilityRequest $request): JsonResponse
    {
        try {
            $record = $this->taxResponsibilityService->createTaxResponsibility($request->validated());

            return $this->successResponse($record, 'Registro de ResponsabilidadTributaria creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/tax-responsibilities/{uuid}',
        summary: 'Actualizar registro de ResponsabilidadTributaria por UUID',
        operationId: 'updateTaxResponsibility',
        tags: ['ResponsabilidadTributaria'],
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
    public function update(UpdateTaxResponsibilityRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->taxResponsibilityService->getTaxResponsibilityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ResponsabilidadTributaria que desea actualizar no existe.', 404);
            }
            $updated = $this->taxResponsibilityService->updateTaxResponsibility($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ResponsabilidadTributaria actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/tax-responsibilities/{uuid}',
        summary: 'Eliminar registro de ResponsabilidadTributaria por UUID',
        operationId: 'destroyTaxResponsibility',
        tags: ['ResponsabilidadTributaria'],
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
            $record = $this->taxResponsibilityService->getTaxResponsibilityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ResponsabilidadTributaria que desea eliminar no existe.', 404);
            }
            $this->taxResponsibilityService->deleteTaxResponsibility($uuid);

            return $this->successResponse(null, 'Registro de ResponsabilidadTributaria eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
