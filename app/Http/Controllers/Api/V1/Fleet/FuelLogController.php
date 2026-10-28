<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\FuelLog\StoreFuelLogRequest;
use App\Http\Requests\Fleet\FuelLog\UpdateFuelLogRequest;
use App\Services\Fleet\FuelLogService;
use App\Services\Fleet\FuelStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para el Control de Combustible, Kilometraje y Saldos.
 */
class FuelLogController extends Controller
{
    public function __construct(
        private readonly FuelLogService $fuelLogService,
        private readonly FuelStatusService $fuelStatusService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-logs',
        summary: 'Listado paginado de registros de combustible',
        operationId: 'listFuelLogs',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Operación realizada con éxito.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');

            $data = $this->fuelLogService->getPaginated($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado de combustible recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-logs/context',
        summary: 'Contexto de precarga (odómetro y saldo anterior) para un vehículo y fecha',
        operationId: 'getFuelLogContext',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Contexto recuperado.')]
    )]
    public function context(Request $request): JsonResponse
    {
        try {
            $vehicleUuid = (string) $request->query('vehicle_uuid', '');
            if ($vehicleUuid === '') {
                return $this->errorResponse('El vehículo es obligatorio.', 422);
            }

            $date = $request->query('date');
            $contexto = $this->fuelLogService->getContext($vehicleUuid, $date ? (string) $date : null);

            return $this->successResponse($contexto, 'Contexto de combustible recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-logs/status',
        summary: 'Estado de combustible por vehículo: km recorridos, tope estimado y km restantes',
        operationId: 'getFuelLogStatus',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Estado recuperado.')]
    )]
    public function status(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $limit = max(1, min(1000, (int) $request->query('limit', '500')));

            $data = $this->fuelStatusService->estadoDeFlota(
                $companyUuid ? (string) $companyUuid : null,
                $thirdPartyUuid ? (string) $thirdPartyUuid : null,
                $limit
            );

            return $this->successResponse($data, 'Estado de combustible recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/fuel-logs/{uuid}',
        summary: 'Detalle de un registro de combustible',
        operationId: 'showFuelLog',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Registro encontrado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->fuelLogService->getByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de combustible solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de combustible recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/fuel-logs',
        summary: 'Crear registro de combustible',
        operationId: 'storeFuelLog',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Creado con éxito.')]
    )]
    public function store(StoreFuelLogRequest $request): JsonResponse
    {
        try {
            $record = $this->fuelLogService->createFuelLog($request->validated());

            return $this->successResponse($record, 'Registro de combustible creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/fuel-logs/{uuid}',
        summary: 'Actualizar registro de combustible',
        operationId: 'updateFuelLog',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function update(UpdateFuelLogRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->fuelLogService->getByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de combustible que desea actualizar no existe.', 404);
            }

            $updated = $this->fuelLogService->updateFuelLog($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de combustible actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/fuel-logs/{uuid}',
        summary: 'Eliminar registro de combustible',
        operationId: 'destroyFuelLog',
        tags: ['Combustible'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado con éxito.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->fuelLogService->getByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de combustible que desea eliminar no existe.', 404);
            }

            $this->fuelLogService->deleteFuelLog($uuid);

            return $this->successResponse(null, 'Registro de combustible eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
