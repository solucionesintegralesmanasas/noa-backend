<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ServiceDeliveryControlSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceDeliveryControlSheet\StoreServiceDeliveryControlSheetRequest;
use App\Http\Requests\ServiceDeliveryControlSheet\UpdateServiceDeliveryControlSheetRequest;
use App\Models\Signature;
use App\Models\Vehicle;
use App\Services\Pdf\PdfService;
use App\Services\ServiceDeliveryControlSheet\ServiceDeliveryControlSheetService;
use App\Services\Signature\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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
        description: 'Cada planilla parte de un proyecto registrado (project_uuid) con fecha de inicio y fin. Soporta N recorridos por planilla diaria y vehículos externos de plataforma. Con solo_cerradas=true solo salen las cerradas.',
        operationId: 'listServiceDeliveryControlSheets',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'project_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrar planillas de un proyecto'),
            new OA\Parameter(name: 'solo_cerradas', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false), description: 'true = solo planillas cerradas (is_active=false y sin hijos abiertos)'),
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
            $soloCerradas = $request->has('solo_cerradas')
                ? filter_var($request->query('solo_cerradas'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
                : false;
            $data = $this->serviceDeliveryControlSheetService->getAllServiceDeliveryControlSheetsWithPagination($perPage, $page, $search, $companyUuid, $projectUuid, $soloCerradas);

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
        path: '/api/v1/control-sheets/service-delivery-control-sheets/funcionarios/buscar',
        summary: 'Buscar funcionarios registrados por número de CC',
        operationId: 'searchFuncionariosServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'cc', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Funcionarios encontrados.'),
            new OA\Response(response: 422, description: 'Parámetro CC requerido.'),
        ]
    )]
    public function buscarFuncionario(Request $request): JsonResponse
    {
        try {
            $request->validate(['cc' => 'required|string|min:3|max:30']);
            $companyUuid = $request->attributes->get('current_company_uuid')
                ?? $request->user()->companies()->first()?->uuid;
            if (! $companyUuid) {
                return $this->errorResponse('Empresa no identificada.', 422);
            }
            $result = $this->serviceDeliveryControlSheetService->searchFuncionarios($companyUuid, $request->input('cc'));

            return $this->successResponse($result, 'Funcionarios encontrados.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}/close-route',
        summary: 'Guardar el cierre de un solo recorrido (cierre parcial por recorrido)',
        operationId: 'closeRouteServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'route_uuid', type: 'string', format: 'uuid', description: 'UUID del recorrido a cerrar'),
                    new OA\Property(property: 'route_index', type: 'integer', nullable: true, description: 'Índice del recorrido (0-based) si no se envía route_uuid'),
                    new OA\Property(property: 'end_time', type: 'string', example: '18:30'),
                    new OA\Property(property: 'ending_kilometer', type: 'number', example: 125680),
                    new OA\Property(property: 'number_of_tolls', type: 'integer', example: 2),
                    new OA\Property(property: 'total_toll_value', type: 'number', example: 45000),
                    new OA\Property(property: 'end_novelty', type: 'string', nullable: true),
                    new OA\Property(property: 'funcionario_nombre', type: 'string', nullable: true, description: 'Nombre y apellido del funcionario del recorrido'),
                    new OA\Property(property: 'funcionario_cc', type: 'string', nullable: true, description: 'Número de CC del funcionario del recorrido'),
                    new OA\Property(property: 'funcionario_signature', type: 'string', nullable: true, description: 'Firma digital base64 PNG del funcionario'),
                    new OA\Property(property: 'conductor_signature', type: 'string', nullable: true, description: 'Firma digital base64 PNG del conductor'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cierre del recorrido guardado con éxito.'),
            new OA\Response(response: 404, description: 'Planilla o recorrido no encontrado.'),
        ]
    )]
    public function closeRoute(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->serviceDeliveryControlSheetService->closeRoute($uuid, $request->all());
            if (! $result['planilla']) {
                return $this->notFoundResponse('La planilla que desea cerrar no existe.');
            }
            if (! $result['route']) {
                return $this->notFoundResponse('El recorrido indicado no pertenece a esta planilla.');
            }

            return $this->successResponse([
                'planilla' => $result['planilla'],
                'route' => $result['route'],
            ], 'Cierre del recorrido guardado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/route-map-capture',
        summary: 'Pegar captura del mapa del recorrido en la planilla del día del conductor',
        description: 'Recibe la captura PNG del mapa tomada en el frontend y la adjunta como imagen ROUTE_MAP de las planillas del conductor en la fecha indicada. El PDF diario la usa en lugar del mapa automático.',
        operationId: 'attachRouteMapServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['third_party_uuid', 'service_date', 'image_base64'],
                properties: [
                    new OA\Property(property: 'third_party_uuid', type: 'string', format: 'uuid', description: 'UUID del conductor'),
                    new OA\Property(property: 'service_date', type: 'string', format: 'date', example: '2026-09-16'),
                    new OA\Property(property: 'image_base64', type: 'string', description: 'Captura PNG en base64 (acepta data URL)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Captura pegada con éxito.'),
            new OA\Response(response: 422, description: 'Sin planilla del conductor para esa fecha o imagen inválida.'),
        ]
    )]
    public function attachRouteMap(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'third_party_uuid' => 'required|uuid|exists:third_parties,uuid',
                'service_date' => 'required|date',
                'image_base64' => 'required|string|max:5500000',
            ]);
            $result = $this->serviceDeliveryControlSheetService->attachRouteMapByDriverDate(
                $request->input('third_party_uuid'),
                $request->input('service_date'),
                $request->input('image_base64')
            );

            return $this->successResponse($result, 'Captura del mapa pegada en la planilla del día.');
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
        summary: 'Descargar PDF mensual de Hoja de Control de Entrega de Servicios (solo días finalizados por defecto)',
        operationId: 'downloadMonthlyPdfServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'year', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'solo_finalizados', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: true), description: 'true = solo días cerrados (is_active=false). false = todos los días.'),
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
                'solo_finalizados' => 'nullable|boolean',
            ]);

            $companyUuid = $request->attributes->get('current_company_uuid');
            $vehicleUuid = $request->query('vehicle_uuid');
            $year = (int) $request->query('year');
            $month = (int) $request->query('month');
            $soloFinalizados = $request->has('solo_finalizados')
                ? filter_var($request->query('solo_finalizados'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true
                : true;

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
            $result = $pdfService->generateMonthlyServiceControlSheetPdf($companyUuid, $vehicleUuid, $year, $month, $soloFinalizados);

            return $result['pdf']->download($result['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/reports/{tipo}/pdf',
        summary: 'Descargar reporte PDF de Control de Servicio solo con días cerrados',
        operationId: 'downloadReportPdfServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['rango', 'vehiculo', 'conductor', 'dia', 'mensual'])),
            new OA\Parameter(name: 'fecha_desde', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha_hasta', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'third_party_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte PDF generado.'),
            new OA\Response(response: 404, description: 'Sin fechas cerradas para los filtros.'),
        ]
    )]
    public function downloadReportPdf(Request $request, string $tipo)
    {
        try {
            $tipos = ['rango', 'vehiculo', 'conductor', 'dia', 'mensual'];
            if (! in_array($tipo, $tipos, true)) {
                return $this->errorResponse('Tipo de reporte inválido.', 422);
            }
            $request->validate([
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
                'fecha' => 'nullable|date',
                'vehicle_uuid' => 'nullable|string',
                'third_party_uuid' => 'nullable|string',
                'year' => 'nullable|integer|min:2020|max:2100',
                'month' => 'nullable|integer|between:1,12',
            ]);
            if ($tipo === 'rango' && (! $request->query('fecha_desde') || ! $request->query('fecha_hasta'))) {
                return $this->errorResponse('El rango requiere fecha_desde y fecha_hasta.', 422);
            }
            if ($tipo === 'vehiculo' && ! $request->query('vehicle_uuid')) {
                return $this->errorResponse('El reporte por vehículo requiere vehicle_uuid.', 422);
            }
            if ($tipo === 'conductor' && ! $request->query('third_party_uuid')) {
                return $this->errorResponse('El reporte por conductor requiere third_party_uuid.', 422);
            }
            if ($tipo === 'dia' && ! $request->query('fecha')) {
                return $this->errorResponse('El reporte por día requiere fecha.', 422);
            }
            if ($tipo === 'mensual' && (! $request->query('year') || ! $request->query('month'))) {
                return $this->errorResponse('El reporte mensual requiere year y month.', 422);
            }

            // El mensual con vehículo usa el generador clásico (historial, peajes,
            // vigencia y firmas por recorrido); sin vehículo usa el genérico.
            if ($tipo === 'mensual' && $request->query('vehicle_uuid')) {
                $companyUuid = $request->attributes->get('current_company_uuid');
                $vehicleUuid = $request->query('vehicle_uuid');
                if (! $companyUuid) {
                    $vehicle = Vehicle::where('uuid', $vehicleUuid)->first();
                    if (! $vehicle) {
                        return $this->errorResponse('Vehículo no encontrado.', 404);
                    }
                    $companyUuid = $vehicle->company_uuid;
                }
                $result = app(PdfService::class)->generateMonthlyServiceControlSheetPdf(
                    $companyUuid, $vehicleUuid, (int) $request->query('year'), (int) $request->query('month'), true
                );

                return $result['pdf']->download($result['file_name']);
            }

            $filtros = array_merge($request->query(), [
                'tipo' => $tipo,
                'company_uuid' => $request->attributes->get('current_company_uuid'),
            ]);
            $result = app(PdfService::class)->generateFilteredServiceControlSheetPdf($filtros);

            return $result['pdf']->download($result['file_name']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/reports/{tipo}/excel',
        summary: 'Descargar reporte Excel de Control de Servicio solo con días cerrados',
        operationId: 'downloadReportExcelServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'tipo', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['rango', 'vehiculo', 'conductor', 'dia', 'mensual'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte Excel generado.'),
        ]
    )]
    public function downloadReportExcel(Request $request, string $tipo)
    {
        try {
            $tipos = ['rango', 'vehiculo', 'conductor', 'dia', 'mensual'];
            if (! in_array($tipo, $tipos, true)) {
                return $this->errorResponse('Tipo de reporte inválido.', 422);
            }
            $filtros = array_merge($request->query(), [
                'tipo' => $tipo,
                'company_uuid' => $request->attributes->get('current_company_uuid'),
            ]);

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ServiceControlSheetExport($filtros),
                'Reporte_Control_'.ucfirst($tipo).'_'.now()->format('Ymd_His').'.xlsx'
            );
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/control-sheets/service-delivery-control-sheets/{uuid}/generate-sign-url',
        summary: 'Generar enlace público temporal para firma del coordinador de servicios',
        operationId: 'generateSignUrlServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Enlace público para firma del coordinador generado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function generateSignUrl(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $isAdmin = $user && method_exists($user, 'hasAnyRole')
                && $user->hasAnyRole(['SUPERADMIN', 'ADMIN_EMPRESA'], 'api');
            if (! $isAdmin) {
                return $this->errorResponse('No tiene permiso para compartir el enlace de firma.', 403);
            }

            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que desea compartir no existe.', 404);
            }

            $apiUrl = URL::temporarySignedRoute(
                'api.v1.public.service-delivery-control-sheets.show',
                now()->addHour(),
                ['uuid' => $uuid],
                false
            );

            $parsedUrl = parse_url($apiUrl);
            parse_str($parsedUrl['query'] ?? '', $queryParameters);

            $frontendBaseUrl = config('app.frontend_url') ?? url('/');

            $publicLink = rtrim($frontendBaseUrl, '/').'/#/firma-documentos/'.$uuid.'?'.http_build_query(array_merge($queryParameters, ['tipo' => 'control']));

            return $this->successResponse([
                'url' => $publicLink,
                'expires_at' => now()->addHour()->toIso8601String(),
            ], 'Enlace público para firma del coordinador generado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/public/service-delivery-control-sheets/{uuid}',
        summary: 'Obtener detalles públicos de la hoja de control para firma del coordinador (Signed URL)',
        operationId: 'showPublicServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalles públicos recuperados.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function showPublic(string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro solicitado no existe.', 404);
            }

            $hasCoordinator = Signature::query()
                ->where('entity_type', 'App\\Models\\ServiceDeliveryControlSheetCoordinator')
                ->where('entity_id', $record->id)
                ->exists();

            return $this->successResponse([
                'uuid' => $record->uuid,
                'id' => $record->id,
                'tipo' => 'control',
                'service_date' => $record->service_date,
                'vehicle_license_plate' => $record->vehicle_license_plate ?? 'N/A',
                'driver_name' => $record->driver_name ?? 'N/A',
                'daily_route' => $record->daily_route ?? 'N/A',
                'project_name' => $record->project?->project_name,
                'has_coordinator_signature' => $hasCoordinator,
            ], 'Detalles públicos de la hoja de control recuperados.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/public/service-delivery-control-sheets/{uuid}',
        summary: 'Firmar hoja de control públicamente como coordinador (Signed URL, una sola firma)',
        operationId: 'signPublicServiceDeliveryControlSheet',
        tags: ['ServiceDeliveryControlSheet'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['signature'],
                properties: [
                    new OA\Property(property: 'signature', type: 'string', description: 'Firma en formato base64 (data:image/png;base64,...)', maxLength: 500000),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Hoja de control firmada por el coordinador con éxito.'),
            new OA\Response(response: 400, description: 'Datos de firma inválidos.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function signPublic(Request $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceDeliveryControlSheetService->getServiceDeliveryControlSheetByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro solicitado no existe.', 404);
            }

            $request->validate([
                'signature' => [
                    'required',
                    'string',
                    'regex:/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/',
                    function (string $attribute, mixed $value, callable $fail): void {
                        [, $base64] = explode(',', $value, 2);
                        $sizeKb = (int) (strlen($base64) * 3 / 4 / 1024);

                        if ($sizeKb > 500) {
                            $fail("La firma no debe superar los 500 KB (actual: {$sizeKb} KB).");
                        }
                    },
                ],
            ]);

            $companyUuid = $record->company_uuid;

            $signatureService = app(SignatureService::class);
            $signature = $signatureService->store([
                'entity_type' => 'App\\Models\\ServiceDeliveryControlSheetCoordinator',
                'entity_id' => $record->id,
                'company_uuid' => $companyUuid,
                'signature' => $request->input('signature'),
            ]);

            return $this->successResponse($signature, 'Hoja de control firmada por el coordinador con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
