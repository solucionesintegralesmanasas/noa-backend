<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\TaxType\StoreTaxTypeRequest;
use App\Http\Requests\Catalogs\TaxType\UpdateTaxTypeRequest;
use App\Services\Catalogs\TaxTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de TipoImpuesto.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TaxTypeController extends Controller
{
    public function __construct(
        private readonly TaxTypeService $taxTypeService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/tax-types',
        summary: 'Consultar listado paginado de TipoImpuesto',
        operationId: 'listTaxTypes',
        tags: ['TipoImpuesto'],
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
            $data = $this->taxTypeService->getAllTaxTypesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de TipoImpuesto recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/tax-types/list',
        summary: 'Obtener catálogo de TipoImpuesto',
        operationId: 'getAllTaxTypes',
        tags: ['TipoImpuesto'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->taxTypeService->getAllTaxTypes();

            return $this->successResponse($data, 'Catálogo de TipoImpuesto recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/tax-types/{uuid}',
        summary: 'Obtener detalle de TipoImpuesto por UUID',
        operationId: 'showTaxType',
        tags: ['TipoImpuesto'],
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
            $record = $this->taxTypeService->getTaxTypeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoImpuesto solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de TipoImpuesto recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/tax-types',
        summary: 'Crear nuevo registro de TipoImpuesto',
        operationId: 'storeTaxType',
        tags: ['TipoImpuesto'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreTaxTypeRequest $request): JsonResponse
    {
        try {
            $record = $this->taxTypeService->createTaxType($request->validated());

            return $this->successResponse($record, 'Registro de TipoImpuesto creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/tax-types/{uuid}',
        summary: 'Actualizar registro de TipoImpuesto por UUID',
        operationId: 'updateTaxType',
        tags: ['TipoImpuesto'],
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
    public function update(UpdateTaxTypeRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->taxTypeService->getTaxTypeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoImpuesto que desea actualizar no existe.', 404);
            }
            $updated = $this->taxTypeService->updateTaxType($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de TipoImpuesto actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/tax-types/{uuid}',
        summary: 'Eliminar registro de TipoImpuesto por UUID',
        operationId: 'destroyTaxType',
        tags: ['TipoImpuesto'],
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
            $record = $this->taxTypeService->getTaxTypeByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TipoImpuesto que desea eliminar no existe.', 404);
            }
            $this->taxTypeService->deleteTaxType($uuid);

            return $this->successResponse(null, 'Registro de TipoImpuesto eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
