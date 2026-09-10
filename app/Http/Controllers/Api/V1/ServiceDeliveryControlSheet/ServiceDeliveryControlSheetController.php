<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ServiceDeliveryControlSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceDeliveryControlSheet\StoreServiceDeliveryControlSheetRequest;
use App\Http\Requests\ServiceDeliveryControlSheet\UpdateServiceDeliveryControlSheetRequest;
use App\Models\Vehicle;
use App\Services\Pdf\PdfService;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para gestión de Hojas de Control de Entrega de Servicios.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceDeliveryControlSheetController extends Controller
{
    public function __construct(
        private readonly ServiceDeliveryControlSheetService $serviceDeliveryControlSheetService
    ) {}

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets',
        summary: 'Consultar listado paginado de Hojas de Control de Entrega de Servicios',
        description: 'Cada planilla parte de un proyecto registrado (project_uuid) con fecha de inicio y fin. Soporta N recorridos por planilla diaria y vehículos externos de plataforma.',
        operationId: 'listServiceDeliveryControlSheets',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'project_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrar planillas de un proyecto'),
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
            $projectUuid = $request->query('project_uuid');
            $data = $this->serviceDeliveryControlSheetService->getAllServiceDeliveryControlSheetsWithPagination($perPage, $page, $search, $companyUuid, $projectUuid);

            return $this->successResponse($data, 'Listado paginado recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/list',
        summary: 'Obtener catálogo de Hojas de Control de Entrega de Servicios',
        operationId: 'getAllServiceDeliveryControlSheets',
        tags: ['ServiceDeliveryControlSheet'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->serviceDeliveryControlSheetService->getAllServiceDeliveryControlSheets();

            return $this->successResponse($data, 'Catálogo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}',
        summary: 'Obtener detalle de Hoja de Control de Entrega de Servicios por UUID',
        operationId: 'showServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
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
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/control-sheets/service-delivery-control-sheets',
        summary: 'Crear nueva Hoja de Control desde un proyecto con N recorridos',
        description: 'Requiere project_uuid (las fechas deben estar dentro de la vigencia del proyecto). type_of_control_sheet admite DIRECTO_CON_LA_EMPRESA, SUBCONTRATADO, CON_VEHICULO_CONTRATADO y EXTERNO_PLATAFORMA. Los recorridos se envían en el arreglo routes.',
        operationId: 'storeServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['company_uuid', 'project_uuid', 'start_date', 'type_of_control_sheet'],
                properties: [
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'project_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'type_of_control_sheet', type: 'string', enum: ['DIRECTO_CON_LA_EMPRESA', 'SUBCONTRATADO', 'CON_VEHICULO_CONTRATADO', 'EXTERNO_PLATAFORMA']),
                    new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'third_party_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'vehicle_license_plate', type: 'string', nullable: true, description: 'Placa para vehículo externo/subcontratado'),
                    new OA\Property(property: 'driver_name_and_surname', type: 'string', nullable: true, description: 'Conductor para vehículo externo/subcontratado'),
                    new OA\Property(property: 'routes', type: 'array', items: new OA\Items(type: 'object'), description: 'N recorridos: origin, destination'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreServiceDeliveryControlSheetRequest $request): JsonResponse
    {
        try {
            $record = $this->serviceDeliveryControlSheetService->createServiceDeliveryControlSheet($request->validated());

            return $this->successResponse($record, 'Registro creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}',
        summary: 'Actualizar registro de Hoja de Control de Entrega de Servicios por UUID',
        operationId: 'updateServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
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
    public function update(UpdateServiceDeliveryControlSheetRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea actualizar no existe.', 404);
            }
            $updated = $this->serviceDeliveryControlSheetService->updateServiceDeliveryControlSheet($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}',
        summary: 'Eliminar registro de Hoja de Control de Entrega de Servicios por UUID',
        operationId: 'destroyServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
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
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea eliminar no existe.', 404);
            }
            $this->serviceDeliveryControlSheetService->deleteServiceDeliveryControlSheet($uuid);

            return $this->successResponse(null, 'Registro eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}/start',
        summary: 'Iniciar registro de Hoja de Control de Entrega de Servicios',
        operationId: 'startServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 200, description: 'Servicio iniciado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function start(Request $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea iniciar no existe.', 404);
            }
            $updated = $this->serviceDeliveryControlSheetService->startServiceDeliveryControlSheet($uuid, $request->all());

            return $this->successResponse($updated, 'Servicio iniciado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}/close',
        summary: 'Finalizar registro de Hoja de Control de Entrega de Servicios',
        operationId: 'closeServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 200, description: 'Servicio finalizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function close(Request $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea finalizar no existe.', 404);
            }
            $updated = $this->serviceDeliveryControlSheetService->closeServiceDeliveryControlSheet($uuid, $request->all());

            return $this->successResponse($updated, 'Servicio finalizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}/pdf',
        summary: 'Descargar PDF diario de Hoja de Control de Entrega de Servicios',
        operationId: 'downloadDailyPdfServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF descargado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function downloadDailyPdf(string $uuid)
    {
        try {
            $pdfService = app(PdfService::class);
            $result = $pdfService->generateDailyServiceControlSheetPdf($uuid);

            return $result['pdf']->download($result['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/monthly/pdf',
        summary: 'Descargar PDF mensual de Hoja de Control de Entrega de Servicios',
        operationId: 'downloadMonthlyPdfServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'year', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF descargado con éxito.'),
            new OA\Response(response: 404, description: 'Vehículo no encontrado.'),
        ]
    )]
    public function downloadMonthlyPdf(Request $request)
    {
        try {
            $request->validate([
                'vehicle_uuid' => 'required|string',
                'year' => 'required|integer',
                'month' => 'required|integer|between:1,12',
            ]);

            $companyUuid = $request->attributes->get('current_company_uuid');
            $vehicleUuid = $request->query('vehicle_uuid');
            $year = (int) $request->query('year');
            $month = (int) $request->query('month');

            if (! $companyUuid) {
                $vehicle = Vehicle::where('uuid', $vehicleUuid)->first();
                if (! $vehicle) {
                    return $this->errorResponse('Vehículo no encontrado.', 404);
                }
                $companyUuid = $vehicle->company_uuid;
            }

            if (! $companyUuid) {
                return $this->errorResponse('No se pudo determinar la empresa asociada al vehículo.', 400);
            }

            $pdfService = app(PdfService::class);
            $result = $pdfService->generateMonthlyServiceControlSheetPdf($companyUuid, $vehicleUuid, $year, $month);

            return $result['pdf']->download($result['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
