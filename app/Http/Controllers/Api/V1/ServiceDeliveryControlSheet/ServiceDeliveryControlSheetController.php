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
