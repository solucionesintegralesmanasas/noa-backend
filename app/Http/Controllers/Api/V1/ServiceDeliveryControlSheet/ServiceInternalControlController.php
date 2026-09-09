<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ServiceDeliveryControlSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceDeliveryControlSheet\StoreServiceInternalControlRequest;
use App\Http\Requests\ServiceDeliveryControlSheet\UpdateServiceInternalControlRequest;
use App\Services\ServiceDeliveryControlSheet\ServiceInternalControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para gestión de Controles Internos de Servicio.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceInternalControlController extends Controller
{
    public function __construct(
        private readonly ServiceInternalControlService $serviceInternalControlService
    ) {}

    #[OA\Get(
        path: '/api/v1/service-internal-controls',
        summary: 'Consultar listado paginado de Controles Internos de Servicio',
        operationId: 'listServiceInternalControls',
        tags: ['ServiceInternalControl'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->serviceInternalControlService->getAllServiceInternalControlsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/service-internal-controls/list',
        summary: 'Obtener catálogo de Controles Internos de Servicio',
        operationId: 'getAllServiceInternalControls',
        tags: ['ServiceInternalControl'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->serviceInternalControlService->getAllServiceInternalControls();

            return $this->successResponse($data, 'Catálogo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/service-internal-controls/{uuid}',
        summary: 'Obtener detalle de Control Interno de Servicio por UUID',
        operationId: 'showServiceInternalControl',
        tags: ['ServiceInternalControl'],
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
            $record = $this->serviceInternalControlService->getServiceInternalControlByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/service-internal-controls',
        summary: 'Crear nuevo registro de Control Interno de Servicio',
        operationId: 'storeServiceInternalControl',
        tags: ['ServiceInternalControl'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreServiceInternalControlRequest $request): JsonResponse
    {
        try {
            $record = $this->serviceInternalControlService->createServiceInternalControl($request->validated());

            return $this->successResponse($record, 'Registro creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/service-internal-controls/{uuid}',
        summary: 'Actualizar registro de Control Interno de Servicio por UUID',
        operationId: 'updateServiceInternalControl',
        tags: ['ServiceInternalControl'],
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
    public function update(UpdateServiceInternalControlRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceInternalControlService->getServiceInternalControlByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea actualizar no existe.', 404);
            }
            $updated = $this->serviceInternalControlService->updateServiceInternalControl($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/service-internal-controls/{uuid}',
        summary: 'Eliminar registro de Control Interno de Servicio por UUID',
        operationId: 'destroyServiceInternalControl',
        tags: ['ServiceInternalControl'],
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
            $record = $this->serviceInternalControlService->getServiceInternalControlByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea eliminar no existe.', 404);
            }
            $this->serviceInternalControlService->deleteServiceInternalControl($uuid);

            return $this->successResponse(null, 'Registro eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
