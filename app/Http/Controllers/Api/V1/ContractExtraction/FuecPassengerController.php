<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ContractExtraction;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractExtraction\FuecPassenger\StoreFuecPassengerRequest;
use App\Http\Requests\ContractExtraction\FuecPassenger\UpdateFuecPassengerRequest;
use App\Services\ContractExtraction\FuecPassengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Pasajeros de FUEC.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FuecPassengerController extends Controller
{
    public function __construct(
        private readonly FuecPassengerService $fuecPassengerService
    ) {}

    #[OA\Get(
        path: '/api/v1/contract-extract/fuec-passengers',
        summary: 'Consultar listado paginado de Pasajeros de FUEC',
        operationId: 'listFuecPassengers',
        tags: ['PasajeroFUEC'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
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
            $data = $this->fuecPassengerService->getAllFuecPassengersWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Pasajeros de FUEC recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/fuec-passengers/list',
        summary: 'Obtener catálogo de Pasajeros de FUEC',
        operationId: 'getAllFuecPassengers',
        tags: ['PasajeroFUEC'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->fuecPassengerService->getAllFuecPassengers();

            return $this->successResponse($data, 'Catálogo de Pasajeros de FUEC recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/fuec-passengers/{uuid}',
        summary: 'Obtener detalle de Pasajero de FUEC por UUID',
        operationId: 'showFuecPassenger',
        tags: ['PasajeroFUEC'],
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
            $record = $this->fuecPassengerService->getFuecPassengerByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Pasajero de FUEC solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Pasajero de FUEC recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/contract-extract/fuec-passengers',
        summary: 'Crear nuevo registro de Pasajero de FUEC',
        operationId: 'storeFuecPassenger',
        tags: ['PasajeroFUEC'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreFuecPassengerRequest $request): JsonResponse
    {
        try {
            $record = $this->fuecPassengerService->createFuecPassenger($request->validated());

            return $this->successResponse($record, 'Registro de Pasajero de FUEC creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/contract-extract/fuec-passengers/{uuid}',
        summary: 'Actualizar registro de Pasajero de FUEC por UUID',
        operationId: 'updateFuecPassenger',
        tags: ['PasajeroFUEC'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateFuecPassengerRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->fuecPassengerService->getFuecPassengerByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Pasajero de FUEC que desea actualizar no existe.', 404);
            }
            $updated = $this->fuecPassengerService->updateFuecPassenger($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Pasajero de FUEC actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/contract-extract/fuec-passengers/{uuid}',
        summary: 'Eliminar registro de Pasajero de FUEC por UUID',
        operationId: 'destroyFuecPassenger',
        tags: ['PasajeroFUEC'],
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
            $record = $this->fuecPassengerService->getFuecPassengerByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Pasajero de FUEC que desea eliminar no existe.', 404);
            }
            $this->fuecPassengerService->deleteFuecPassenger($uuid);

            return $this->successResponse(null, 'Registro de Pasajero de FUEC eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
