<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Fleet\Maintenance\UpdateMaintenanceRequest;
use App\Services\Fleet\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de RegistroMantenimiento.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/maintenances',
        summary: 'Consultar listado paginado de RegistroMantenimiento',
        operationId: 'listMaintenances',
        tags: ['RegistroMantenimiento'],
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
            $data = $this->maintenanceService->getAllMaintenancesWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de RegistroMantenimiento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/maintenances/list',
        summary: 'Obtener catálogo de RegistroMantenimiento',
        operationId: 'getAllMaintenances',
        tags: ['RegistroMantenimiento'],
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
            $data = $this->maintenanceService->getAllMaintenances($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de RegistroMantenimiento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/maintenances/{uuid}',
        summary: 'Obtener detalle de RegistroMantenimiento por UUID',
        operationId: 'showMaintenance',
        tags: ['RegistroMantenimiento'],
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
            $record = $this->maintenanceService->getMaintenanceByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de RegistroMantenimiento solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de RegistroMantenimiento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/maintenances',
        summary: 'Crear nuevo registro de RegistroMantenimiento',
        operationId: 'storeMaintenance',
        tags: ['RegistroMantenimiento'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['company_uuid', 'vehicle_uuid', 'maintenance_type', 'mileage', 'service_description', 'maintenance_date'],
                properties: [
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '7a8e71fa-6509-41bc-a288-f1f3ad450b79'),
                    new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                    new OA\Property(property: 'maintenance_type', type: 'string', enum: ['PREVENTIVA', 'CORRECTIVA', 'OTRO'], example: 'PREVENTIVA'),
                    new OA\Property(property: 'mileage', type: 'integer', minimum: 0, example: 45200),
                    new OA\Property(property: 'service_description', type: 'string', example: 'Cambio de aceite de motor y filtros'),
                    new OA\Property(property: 'mechanic_name', type: 'string', nullable: true, maxLength: 150, example: 'José Pérez'),
                    new OA\Property(property: 'workshop_name', type: 'string', nullable: true, maxLength: 150, example: 'Taller Central de Flotas'),
                    new OA\Property(property: 'maintenance_date', type: 'string', format: 'date', example: '2026-05-31'),
                    new OA\Property(property: 'labor_cost', type: 'number', format: 'float', nullable: true, example: 120000.50),
                    new OA\Property(property: 'parts_cost', type: 'number', format: 'float', nullable: true, example: 85000.00),
                    new OA\Property(property: 'invoice_number', type: 'string', nullable: true, maxLength: 50, example: 'FAC-98745'),
                    new OA\Property(property: 'next_maintenance_date', type: 'string', format: 'date', nullable: true, example: '2026-08-31'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Se sugiere revisar frenos en próxima sesión'),
                    new OA\Property(property: 'status', type: 'string', enum: ['Pendiente', 'Finalizado', 'Anulado'], example: 'Finalizado'),
                    new OA\Property(
                        property: 'parts',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['part_name', 'quantity', 'unit_cost'],
                            properties: [
                                new OA\Property(property: 'part_name', type: 'string', maxLength: 150, example: 'Filtro de Aceite sintético'),
                                new OA\Property(property: 'part_code', type: 'string', nullable: true, maxLength: 50, example: 'FLT-50W30'),
                                new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 1.0),
                                new OA\Property(property: 'unit_cost', type: 'number', format: 'float', example: 45000.00),
                                new OA\Property(property: 'supplier_uuid', type: 'string', format: 'uuid', nullable: true, example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Repuesto original homologado'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        try {
            $record = $this->maintenanceService->createMaintenance($request->validated());

            return $this->successResponse($record, 'Registro de RegistroMantenimiento creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/maintenances/{uuid}',
        summary: 'Actualizar registro de RegistroMantenimiento por UUID',
        operationId: 'updateMaintenance',
        tags: ['RegistroMantenimiento'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'maintenance_type', type: 'string', enum: ['PREVENTIVA', 'CORRECTIVA', 'OTRO'], example: 'PREVENTIVA'),
                    new OA\Property(property: 'mileage', type: 'integer', minimum: 0, example: 45500),
                    new OA\Property(property: 'service_description', type: 'string', example: 'Mantenimiento preventivo general y balanceo'),
                    new OA\Property(property: 'mechanic_name', type: 'string', nullable: true, maxLength: 150, example: 'José Pérez S.'),
                    new OA\Property(property: 'workshop_name', type: 'string', nullable: true, maxLength: 150, example: 'Taller Central de Flotas S.A.'),
                    new OA\Property(property: 'maintenance_date', type: 'string', format: 'date', example: '2026-05-31'),
                    new OA\Property(property: 'labor_cost', type: 'number', format: 'float', nullable: true, example: 130000.00),
                    new OA\Property(property: 'parts_cost', type: 'number', format: 'float', nullable: true, example: 95000.00),
                    new OA\Property(property: 'invoice_number', type: 'string', nullable: true, maxLength: 50, example: 'FAC-98745B'),
                    new OA\Property(property: 'next_maintenance_date', type: 'string', format: 'date', nullable: true, example: '2026-09-30'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Se realizó alineación de llantas traseras'),
                    new OA\Property(property: 'status', type: 'string', enum: ['Pendiente', 'Finalizado', 'Anulado'], example: 'Finalizado'),
                    new OA\Property(
                        property: 'parts',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['part_name', 'quantity', 'unit_cost'],
                            properties: [
                                new OA\Property(property: 'part_name', type: 'string', maxLength: 150, example: 'Filtro de Aceite sintético'),
                                new OA\Property(property: 'part_code', type: 'string', nullable: true, maxLength: 50, example: 'FLT-50W30'),
                                new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 1.0),
                                new OA\Property(property: 'unit_cost', type: 'number', format: 'float', example: 45000.00),
                                new OA\Property(property: 'supplier_uuid', type: 'string', format: 'uuid', nullable: true, example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Repuesto original homologado'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateMaintenanceRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->maintenanceService->getMaintenanceByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de RegistroMantenimiento que desea actualizar no existe.', 404);
            }
            $updated = $this->maintenanceService->updateMaintenance($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de RegistroMantenimiento actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/maintenances/{uuid}',
        summary: 'Eliminar registro de RegistroMantenimiento por UUID',
        operationId: 'destroyMaintenance',
        tags: ['RegistroMantenimiento'],
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
            $record = $this->maintenanceService->getMaintenanceByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de RegistroMantenimiento que desea eliminar no existe.', 404);
            }
            $this->maintenanceService->deleteMaintenance($uuid);

            return $this->successResponse(null, 'Registro de RegistroMantenimiento eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
