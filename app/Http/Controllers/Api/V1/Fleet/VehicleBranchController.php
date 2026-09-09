<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\VehicleBranch\StoreVehicleBranchRequest;
use App\Http\Requests\Fleet\VehicleBranch\UpdateVehicleBranchRequest;
use App\Services\Fleet\VehicleBranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de sucursales vinculadas a los vehículos (vehicles_branches).
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-06-11
 */
class VehicleBranchController extends Controller
{
    public function __construct(
        private readonly VehicleBranchService $vehicleBranchService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles-branches',
        summary: 'Consultar listado paginado de sucursales vinculadas a vehículos',
        operationId: 'listVehicleBranches',
        tags: ['Vehículos Sucursales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
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
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $vehicleUuid = $request->query('vehicle_uuid') ?? $request->input('filter.vehicle_uuid');
            $data = $this->vehicleBranchService->getAllVehicleBranchesWithPagination($perPage, $page, $companyUuid, $vehicleUuid);

            return $this->successResponse($data, 'Listado paginado de sucursales de vehículos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles-branches/list',
        summary: 'Obtener catálogo de sucursales vinculadas a vehículos',
        operationId: 'getAllVehicleBranches',
        tags: ['Vehículos Sucursales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $vehicleUuid = $request->query('vehicle_uuid') ?? $request->input('filter.vehicle_uuid');
            $data = $this->vehicleBranchService->getAllVehicleBranches($companyUuid, $vehicleUuid);

            return $this->successResponse($data, 'Catálogo de sucursales de vehículos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles-branches/{uuid}',
        summary: 'Obtener detalle de sucursal vinculada a vehículo por UUID',
        operationId: 'showVehicleBranch',
        tags: ['Vehículos Sucursales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro encontrado.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleBranchService->getVehicleBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de sucursal del vehículo solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de sucursal de vehículo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/vehicles-branches',
        summary: 'Crear nuevo registro de sucursal vinculada a vehículo',
        operationId: 'storeVehicleBranch',
        tags: ['Vehículos Sucursales'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function store(StoreVehicleBranchRequest $request): JsonResponse
    {
        try {
            $record = $this->vehicleBranchService->createVehicleBranch($request->validated());

            return $this->successResponse($record, 'Registro de sucursal de vehículo creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/vehicles-branches/{uuid}',
        summary: 'Actualizar registro de sucursal vinculada a vehículo por UUID',
        operationId: 'updateVehicleBranch',
        tags: ['Vehículos Sucursales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function update(UpdateVehicleBranchRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleBranchService->getVehicleBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de sucursal del vehículo que desea actualizar no existe.', 404);
            }
            $updated = $this->vehicleBranchService->updateVehicleBranch($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de sucursal de vehículo actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/vehicles-branches/{uuid}',
        summary: 'Eliminar registro de sucursal vinculada a vehículo por UUID',
        operationId: 'destroyVehicleBranch',
        tags: ['Vehículos Sucursales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleBranchService->getVehicleBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de sucursal del vehículo que desea eliminar no existe.', 404);
            }
            $this->vehicleBranchService->deleteVehicleBranch($uuid);

            return $this->successResponse(null, 'Registro de sucursal de vehículo eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
