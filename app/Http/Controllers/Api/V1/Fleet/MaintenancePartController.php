<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\MaintenancePart\StoreMaintenancePartRequest;
use App\Http\Requests\Fleet\MaintenancePart\UpdateMaintenancePartRequest;
use App\Services\Fleet\MaintenancePartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de RepuestoMantenimiento.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class MaintenancePartController extends Controller
{
    public function __construct(
        private readonly MaintenancePartService $maintenancePartService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/maintenance-parts',
        summary: 'Consultar listado paginado de RepuestoMantenimiento',
        operationId: 'listMaintenanceParts',
        tags: ['RepuestoMantenimiento'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->maintenancePartService->getAllMaintenancePartsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de RepuestoMantenimiento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/maintenance-parts/list',
        summary: 'Obtener catálogo de RepuestoMantenimiento',
        operationId: 'getAllMaintenanceParts',
        tags: ['RepuestoMantenimiento'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->maintenancePartService->getAllMaintenanceParts($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de RepuestoMantenimiento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/maintenance-parts/{uuid}',
        summary: 'Obtener detalle de RepuestoMantenimiento por UUID',
        operationId: 'showMaintenancePart',
        tags: ['RepuestoMantenimiento'],
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
            $record = $this->maintenancePartService->getMaintenancePartByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de RepuestoMantenimiento solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de RepuestoMantenimiento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/maintenance-parts',
        summary: 'Crear nuevo registro de RepuestoMantenimiento',
        operationId: 'storeMaintenancePart',
        tags: ['RepuestoMantenimiento'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreMaintenancePartRequest $request): JsonResponse
    {
        try {
            $record = $this->maintenancePartService->createMaintenancePart($request->validated());

            return $this->successResponse($record, 'Registro de RepuestoMantenimiento creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/maintenance-parts/{uuid}',
        summary: 'Actualizar registro de RepuestoMantenimiento por UUID',
        operationId: 'updateMaintenancePart',
        tags: ['RepuestoMantenimiento'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateMaintenancePartRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->maintenancePartService->getMaintenancePartByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de RepuestoMantenimiento que desea actualizar no existe.', 404);
            }
            $updated = $this->maintenancePartService->updateMaintenancePart($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de RepuestoMantenimiento actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/maintenance-parts/{uuid}',
        summary: 'Eliminar registro de RepuestoMantenimiento por UUID',
        operationId: 'destroyMaintenancePart',
        tags: ['RepuestoMantenimiento'],
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
            $record = $this->maintenancePartService->getMaintenancePartByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de RepuestoMantenimiento que desea eliminar no existe.', 404);
            }
            $this->maintenancePartService->deleteMaintenancePart($uuid);

            return $this->successResponse(null, 'Registro de RepuestoMantenimiento eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
