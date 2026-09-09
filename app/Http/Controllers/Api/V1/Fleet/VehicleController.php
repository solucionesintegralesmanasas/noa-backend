<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Fleet\Vehicle\UpdateVehicleRequest;
use App\Services\Fleet\PreventiveMaintenanceService;
use App\Services\Fleet\VehicleService;
use App\Services\Pdf\PdfService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Vehiculo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleService $vehicleService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles',
        summary: 'Consultar listado paginado de Vehiculo',
        operationId: 'listVehicles',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'third_party_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
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
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->vehicleService->getAllVehiclesWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de Vehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/list',
        summary: 'Obtener catálogo de Vehiculo',
        operationId: 'getAllVehicles',
        tags: ['Vehiculo'],
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
            
            \Illuminate\Support\Facades\Log::info('VehicleController@list called', [
                'company_uuid' => $companyUuid,
                'third_party_uuid' => $thirdPartyUuid,
                'user_id' => \Illuminate\Support\Facades\Auth::id()
            ]);

            $data = $this->vehicleService->getAllVehicles($companyUuid, $thirdPartyUuid);

            \Illuminate\Support\Facades\Log::info('VehicleController@list result count: ' . $data->count());

            return $this->successResponse($data, 'Catálogo de Vehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}',
        summary: 'Obtener detalle de Vehiculo por UUID',
        operationId: 'showVehicle',
        tags: ['Vehiculo'],
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
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Vehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}/profile',
        summary: 'Obtener el perfil completo de un Vehículo por UUID',
        operationId: 'profileVehicle',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Perfil de Vehículo encontrado.'),
            new OA\Response(response: 404, description: 'Vehículo no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function profile(string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleService->getVehicleProfile($uuid);

            return $this->successResponse($record, 'Perfil de Vehiculo recuperado con éxito.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('El vehículo solicitado no existe.', 404);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/vehicles',
        summary: 'Crear nuevo registro de Vehiculo',
        operationId: 'storeVehicle',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        try {
            $record = $this->vehicleService->createVehicle($request->validated());

            return $this->successResponse($record, 'Registro de Vehiculo creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/vehicles/{uuid}',
        summary: 'Actualizar registro de Vehiculo por UUID',
        operationId: 'updateVehicle',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateVehicleRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo que desea actualizar no existe.', 404);
            }
            $updated = $this->vehicleService->updateVehicle($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Vehiculo actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/vehicles/{uuid}',
        summary: 'Eliminar registro de Vehiculo por UUID',
        operationId: 'destroyVehicle',
        tags: ['Vehiculo'],
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
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo que desea eliminar no existe.', 404);
            }
            $this->vehicleService->deleteVehicle($uuid);

            return $this->successResponse(null, 'Registro de Vehiculo eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/fleet-management/vehicles/{uuid}/toggle-status',
        summary: 'Alternar el estado de activación de un Vehiculo',
        operationId: 'toggleVehicleStatus',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado alternado con éxito.'),
            new OA\Response(response: 404, description: 'Vehiculo no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo no existe.', 404);
            }
            $updated = $this->vehicleService->toggleVehicleStatus($uuid);

            return $this->successResponse($updated, 'Estado del Vehiculo alternado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}/technical-sheet/pdf',
        summary: 'Exportar ficha técnica de Vehiculo en formato PDF',
        operationId: 'technicalSheetVehiclePdf',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function technicalSheetPdf(string $uuid, PdfService $pdfService)
    {
        try {
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo solicitado no existe.', 404);
            }

            $data = $pdfService->generateVehicleTechnicalSheetPdf($uuid);

            return $data['pdf']->stream($data['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}/history',
        summary: 'Obtener historial completo del vehículo con todas sus relaciones',
        operationId: 'vehicleHistory',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historial recuperado con éxito.'),
            new OA\Response(response: 404, description: 'Vehículo no encontrado.'),
        ]
    )]
    public function history(string $uuid): JsonResponse
    {
        try {
            $data = $this->vehicleService->getVehicleHistory($uuid);

            return $this->successResponse($data, 'Historial completo del vehículo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}/history/pdf',
        summary: 'Exportar Hoja de Vida Vehicular en PDF',
        operationId: 'vehicleHistoryPdf',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado con éxito.'),
            new OA\Response(response: 404, description: 'Vehículo no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function historyPdf(string $uuid, PdfService $pdfService)
    {
        try {
            $data = $pdfService->generateVehicleHistoryPdf($uuid);

            return $data['pdf']->stream($data['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}/maintenance-history/pdf',
        summary: 'Exportar Hoja de Vida de Mantenimiento de Vehículo en PDF',
        operationId: 'vehicleMaintenanceHistoryPdf',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF de mantenimiento generado con éxito.'),
            new OA\Response(response: 404, description: 'Vehículo no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function maintenanceHistoryPdf(string $uuid, PdfService $pdfService)
    {
        try {
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo solicitado no existe.', 404);
            }

            $data = $pdfService->generateMaintenanceHistoryPdf($uuid);

            return $data['pdf']->stream($data['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicles/{uuid}/handover-record/pdf',
        summary: 'Exportar Acta de Entrega de Vehículo en PDF',
        operationId: 'vehicleHandoverRecordPdf',
        tags: ['Vehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado con éxito.'),
            new OA\Response(response: 404, description: 'Vehículo no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno.'),
        ]
    )]
    public function handoverRecordPdf(string $uuid, PdfService $pdfService)
    {
        try {
            $record = $this->vehicleService->getVehicleByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Vehiculo solicitado no existe.', 404);
            }

            $data = $pdfService->generateHandoverRecordPdf($uuid);

            return $data['pdf']->stream($data['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function maintenanceForecast(string $uuid, PreventiveMaintenanceService $forecastService): JsonResponse
    {
        try {
            $data = $forecastService->calculateForecast($uuid);

            return $this->successResponse($data, 'Pronóstico de mantenimiento preventivo calculado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
