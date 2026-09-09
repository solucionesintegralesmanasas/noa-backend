<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\City\StoreCityRequest;
use App\Http\Requests\Catalogs\City\UpdateCityRequest;
use App\Services\Catalogs\CityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Ciudad.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class CityController extends Controller
{
    public function __construct(
        private readonly CityService $cityService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/cities',
        summary: 'Consultar listado paginado de Ciudad',
        operationId: 'listCities',
        tags: ['Ciudad'],
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
            $data = $this->cityService->getAllCitiesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Ciudad recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/cities/list',
        summary: 'Obtener catálogo de Ciudad',
        operationId: 'getAllCities',
        tags: ['Ciudad'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->cityService->getAllCities();

            return $this->successResponse($data, 'Catálogo de Ciudad recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/cities/{uuid}',
        summary: 'Obtener detalle de Ciudad por UUID',
        operationId: 'showCity',
        tags: ['Ciudad'],
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
            $record = $this->cityService->getCityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Ciudad solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Ciudad recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/cities',
        summary: 'Crear nuevo registro de Ciudad',
        operationId: 'storeCity',
        tags: ['Ciudad'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreCityRequest $request): JsonResponse
    {
        try {
            $record = $this->cityService->createCity($request->validated());

            return $this->successResponse($record, 'Registro de Ciudad creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/cities/{uuid}',
        summary: 'Actualizar registro de Ciudad por UUID',
        operationId: 'updateCity',
        tags: ['Ciudad'],
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
    public function update(UpdateCityRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->cityService->getCityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Ciudad que desea actualizar no existe.', 404);
            }
            $updated = $this->cityService->updateCity($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Ciudad actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/cities/{uuid}',
        summary: 'Eliminar registro de Ciudad por UUID',
        operationId: 'destroyCity',
        tags: ['Ciudad'],
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
            $record = $this->cityService->getCityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Ciudad que desea eliminar no existe.', 404);
            }
            $this->cityService->deleteCity($uuid);

            return $this->successResponse(null, 'Registro de Ciudad eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
