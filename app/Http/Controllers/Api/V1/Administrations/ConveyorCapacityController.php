<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\ConveyorCapacity\StoreConveyorCapacityRequest;
use App\Http\Requests\Administrations\ConveyorCapacity\UpdateConveyorCapacityRequest;
use App\Services\Administrations\ConveyorCapacityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Capacidades de Transporte.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ConveyorCapacityController extends Controller
{
    public function __construct(private readonly ConveyorCapacityService $conveyorCapacityService) {}

    #[OA\Get(
        path: '/api/v1/administration/conveyor-capacities',
        summary: 'Listar capacidades autorizadas',
        operationId: 'listConveyorCapacities',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado recuperado.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');

            $data = $this->conveyorCapacityService->getAllConveyorCapacitiesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Capacidades obtenidas exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/conveyor-capacities/list',
        summary: 'Obtener catálogo de CapacidadTransporte',
        operationId: 'listCapacidadTransporte',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->conveyorCapacityService->getAllConveyorCapacities();

            return $this->successResponse($data, 'Catálogo completo de capacidad transportadora obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/conveyor-capacities',
        summary: 'Registrar niveles de capacidad',
        operationId: 'storeConveyorCapacity',
        tags: ['CapacidadTransporte'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Capacidad registrada.')]
    )]
    public function store(StoreConveyorCapacityRequest $request): JsonResponse
    {
        try {
            $record = $this->conveyorCapacityService->createConveyorCapacity($request->validated());

            return $this->successResponse($record, 'Configuración de capacidad creada.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/conveyor-capacities/{uuid}',
        summary: 'Consultar detalle de capacidad',
        operationId: 'showConveyorCapacity',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Detalle recuperado.'),
            new OA\Response(response: 404, description: 'Capacidad no encontrada.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->conveyorCapacityService->getConveyorCapacityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de capacidad no existe.', 404);
            }

            return $this->successResponse($record, 'Información de capacidad obtenida exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/conveyor-capacities/{uuid}',
        summary: 'Actualizar capacidad operativa',
        operationId: 'updateConveyorCapacity',
        tags: ['CapacidadTransporte'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualización exitosa.')]
    )]
    public function update(UpdateConveyorCapacityRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->conveyorCapacityService->getConveyorCapacityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro indicado no existe.', 404);
            }
            $updated = $this->conveyorCapacityService->updateConveyorCapacity($uuid, $request->validated());

            return $this->successResponse($updated, 'Capacidad actualizada correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/conveyor-capacities/{uuid}',
        summary: 'Eliminar registro de capacidad',
        operationId: 'destroyConveyorCapacity',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Eliminado con éxito.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->conveyorCapacityService->getConveyorCapacityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La capacidad ya ha sido eliminada o no existe.', 404);
            }
            $this->conveyorCapacityService->deleteConveyorCapacity($uuid);

            return $this->successResponse(null, 'Registro de capacidad retirado del sistema.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/conveyor-capacities/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo de CapacidadTransporte',
        operationId: 'toggleStatusCapacidadTransporte',
        tags: ['CapacidadTransporte'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->conveyorCapacityService->getConveyorCapacityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que intenta modificar no existe.', 404);
            }
            $updated = $this->conveyorCapacityService->toggleConveyorCapacityStatus($uuid);
            $activo = $updated->is_active ?? $updated->status ?? false;
            $estado = $activo ? 'activado' : 'desactivado';

            return $this->successResponse($updated, ucfirst('capacidad transportadora').' '.$estado.' correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
