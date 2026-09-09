<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\FleetServiceContract\StoreFleetServiceContractRequest;
use App\Http\Requests\Procedure\FleetServiceContract\UpdateFleetServiceContractRequest;
use App\Services\Procedure\FleetServiceContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ContratoGestionFlota.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FleetServiceContractController extends Controller
{
    public function __construct(
        private readonly FleetServiceContractService $fleetServiceContractService
    ) {}

    #[OA\Get(
        path: '/api/v1/procedure/fleet-service-contracts',
        summary: 'Consultar listado paginado de ContratoGestionFlota',
        operationId: 'listFleetServiceContracts',
        tags: ['ContratoGestionFlota'],
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
            $data = $this->fleetServiceContractService->getAllFleetServiceContractsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de ContratoGestionFlota recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/fleet-service-contracts/list',
        summary: 'Obtener catálogo de ContratoGestionFlota',
        operationId: 'getAllFleetServiceContracts',
        tags: ['ContratoGestionFlota'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->fleetServiceContractService->getAllFleetServiceContracts();

            return $this->successResponse($data, 'Catálogo de ContratoGestionFlota recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/fleet-service-contracts/{uuid}',
        summary: 'Obtener detalle de ContratoGestionFlota por UUID',
        operationId: 'showFleetServiceContract',
        tags: ['ContratoGestionFlota'],
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
            $record = $this->fleetServiceContractService->getFleetServiceContractByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ContratoGestionFlota solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ContratoGestionFlota recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/procedure/fleet-service-contracts',
        summary: 'Crear nuevo registro de ContratoGestionFlota',
        operationId: 'storeFleetServiceContract',
        tags: ['ContratoGestionFlota'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreFleetServiceContractRequest $request): JsonResponse
    {
        try {
            $record = $this->fleetServiceContractService->createFleetServiceContract($request->validated());

            return $this->successResponse($record, 'Registro de ContratoGestionFlota creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/procedure/fleet-service-contracts/{uuid}',
        summary: 'Actualizar registro de ContratoGestionFlota por UUID',
        operationId: 'updateFleetServiceContract',
        tags: ['ContratoGestionFlota'],
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
    public function update(UpdateFleetServiceContractRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->fleetServiceContractService->getFleetServiceContractByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ContratoGestionFlota que desea actualizar no existe.', 404);
            }
            $updated = $this->fleetServiceContractService->updateFleetServiceContract($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ContratoGestionFlota actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/procedure/fleet-service-contracts/{uuid}',
        summary: 'Eliminar registro de ContratoGestionFlota por UUID',
        operationId: 'destroyFleetServiceContract',
        tags: ['ContratoGestionFlota'],
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
            $record = $this->fleetServiceContractService->getFleetServiceContractByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ContratoGestionFlota que desea eliminar no existe.', 404);
            }
            $this->fleetServiceContractService->deleteFleetServiceContract($uuid);

            return $this->successResponse(null, 'Registro de ContratoGestionFlota eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
