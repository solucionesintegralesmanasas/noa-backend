<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\MeasurementUnit\StoreMeasurementUnitRequest;
use App\Http\Requests\Catalogs\MeasurementUnit\UpdateMeasurementUnitRequest;
use App\Services\Catalogs\MeasurementUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de UnidadMedida.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class MeasurementUnitController extends Controller
{
    public function __construct(
        private readonly MeasurementUnitService $measurementUnitService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/measurement-units',
        summary: 'Consultar listado paginado de UnidadMedida',
        operationId: 'listMeasurementUnits',
        tags: ['UnidadMedida'],
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
            $data = $this->measurementUnitService->getAllMeasurementUnitsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de UnidadMedida recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/measurement-units/list',
        summary: 'Obtener catálogo de UnidadMedida',
        operationId: 'getAllMeasurementUnits',
        tags: ['UnidadMedida'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->measurementUnitService->getAllMeasurementUnits();

            return $this->successResponse($data, 'Catálogo de UnidadMedida recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/measurement-units/{uuid}',
        summary: 'Obtener detalle de UnidadMedida por UUID',
        operationId: 'showMeasurementUnit',
        tags: ['UnidadMedida'],
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
            $record = $this->measurementUnitService->getMeasurementUnitByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de UnidadMedida solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de UnidadMedida recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/measurement-units',
        summary: 'Crear nuevo registro de UnidadMedida',
        operationId: 'storeMeasurementUnit',
        tags: ['UnidadMedida'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreMeasurementUnitRequest $request): JsonResponse
    {
        try {
            $record = $this->measurementUnitService->createMeasurementUnit($request->validated());

            return $this->successResponse($record, 'Registro de UnidadMedida creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/measurement-units/{uuid}',
        summary: 'Actualizar registro de UnidadMedida por UUID',
        operationId: 'updateMeasurementUnit',
        tags: ['UnidadMedida'],
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
    public function update(UpdateMeasurementUnitRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->measurementUnitService->getMeasurementUnitByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de UnidadMedida que desea actualizar no existe.', 404);
            }
            $updated = $this->measurementUnitService->updateMeasurementUnit($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de UnidadMedida actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/measurement-units/{uuid}',
        summary: 'Eliminar registro de UnidadMedida por UUID',
        operationId: 'destroyMeasurementUnit',
        tags: ['UnidadMedida'],
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
            $record = $this->measurementUnitService->getMeasurementUnitByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de UnidadMedida que desea eliminar no existe.', 404);
            }
            $this->measurementUnitService->deleteMeasurementUnit($uuid);

            return $this->successResponse(null, 'Registro de UnidadMedida eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
