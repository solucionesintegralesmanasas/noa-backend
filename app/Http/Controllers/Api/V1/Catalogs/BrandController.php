<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\Brand\StoreBrandRequest;
use App\Http\Requests\Catalogs\Brand\UpdateBrandRequest;
use App\Services\Catalogs\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Marca.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $brandService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/brands',
        summary: 'Consultar listado paginado de Marca',
        operationId: 'listBrands',
        tags: ['Marca'],
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
            $data = $this->brandService->getAllBrandsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Marca recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/brands/list',
        summary: 'Obtener catálogo de Marca',
        operationId: 'getAllBrands',
        tags: ['Marca'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->brandService->getAllBrands();

            return $this->successResponse($data, 'Catálogo de Marca recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/brands/{uuid}',
        summary: 'Obtener detalle de Marca por UUID',
        operationId: 'showBrand',
        tags: ['Marca'],
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
            $record = $this->brandService->getBrandByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Marca solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Marca recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/brands',
        summary: 'Crear nuevo registro de Marca',
        operationId: 'storeBrand',
        tags: ['Marca'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreBrandRequest $request): JsonResponse
    {
        try {
            $record = $this->brandService->createBrand($request->validated());

            return $this->successResponse($record, 'Registro de Marca creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/brands/{uuid}',
        summary: 'Actualizar registro de Marca por UUID',
        operationId: 'updateBrand',
        tags: ['Marca'],
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
    public function update(UpdateBrandRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->brandService->getBrandByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Marca que desea actualizar no existe.', 404);
            }
            $updated = $this->brandService->updateBrand($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Marca actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/brands/{uuid}',
        summary: 'Eliminar registro de Marca por UUID',
        operationId: 'destroyBrand',
        tags: ['Marca'],
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
            $record = $this->brandService->getBrandByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Marca que desea eliminar no existe.', 404);
            }
            $this->brandService->deleteBrand($uuid);

            return $this->successResponse(null, 'Registro de Marca eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
