<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\VehicleInspection\StoreVehicleInspectionRequest;
use App\Http\Requests\Fleet\VehicleInspection\UpdateVehicleInspectionRequest;
use App\Models\Signature;
use App\Services\Fleet\VehicleInspectionService;
use App\Services\Pdf\PdfService;
use App\Services\Signature\SignatureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de InspeccionVehiculo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class VehicleInspectionController extends Controller
{
    public function __construct(
        private readonly VehicleInspectionService $inspectionService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-inspections',
        summary: 'Consultar listado paginado de InspeccionVehiculo',
        operationId: 'listVehicleInspections',
        tags: ['InspeccionVehiculo'],
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

            $filter = $request->query('filter') ?? $request->input('filter') ?? [];
            $companyUuid = $request->query('company_uuid') ?? ($filter['company_uuid'] ?? null);
            $thirdPartyUuid = $request->query('third_party_uuid') ?? ($filter['third_party_uuid'] ?? null);
            $vehicleUuid = $request->query('vehicle_uuid') ?? ($filter['vehicle_uuid'] ?? null);

            $data = $this->inspectionService->getAllVehicleInspectionsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid, $vehicleUuid);

            return $this->successResponse($data, 'Listado paginado de InspeccionVehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-inspections/list',
        summary: 'Obtener catálogo de InspeccionVehiculo',
        operationId: 'getAllVehicleInspections',
        tags: ['InspeccionVehiculo'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $filter = $request->query('filter') ?? $request->input('filter') ?? [];
            $companyUuid = $request->query('company_uuid') ?? ($filter['company_uuid'] ?? null);
            $thirdPartyUuid = $request->query('third_party_uuid') ?? ($filter['third_party_uuid'] ?? null);
            $vehicleUuid = $request->query('vehicle_uuid') ?? ($filter['vehicle_uuid'] ?? null);

            $data = $this->inspectionService->getAllVehicleInspections($companyUuid, $thirdPartyUuid, $vehicleUuid);

            return $this->successResponse($data, 'Catálogo de InspeccionVehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-inspections/check-today',
        summary: 'Verificar si existe inspección de un vehículo en una fecha (una al día)',
        operationId: 'checkTodayVehicleInspection',
        tags: ['InspeccionVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Fecha a verificar (Y-m-d)'),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Verificación realizada.'),
            new OA\Response(response: 422, description: 'Parámetros inválidos.'),
        ]
    )]
    public function checkToday(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'vehicle_uuid' => 'required|string',
                'date' => 'required|date',
                'company_uuid' => 'nullable|string',
            ]);

            $record = $this->inspectionService->existsInspectionForDate(
                $validated['vehicle_uuid'],
                $validated['date'],
                $validated['company_uuid'] ?? null
            );

            return $this->successResponse([
                'exists' => $record !== null,
                'inspection' => $record,
            ], $record ? 'Inspección del día encontrada.' : 'Sin inspección registrada para esa fecha.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-inspections/{uuid}',
        summary: 'Obtener detalle de InspeccionVehiculo por UUID',
        operationId: 'showVehicleInspection',
        tags: ['InspeccionVehiculo'],
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
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de InspeccionVehiculo solicitado no existe.', 404);
            }

            $hasInspector = Signature::query()->where('entity_type', 'vehicle_inspection_inspector')->where('entity_id', $record->id)->exists();
            $hasCoordinator = Signature::query()->where('entity_type', 'vehicle_inspection_coordinator')->where('entity_id', $record->id)->exists();
            if (! $hasCoordinator) {
                $hasCoordinator = Signature::query()->where('entity_type', 'vehicle_inspection')->where('entity_id', $record->id)->exists();
            }

            $recordArray = $record->toArray();
            $recordArray['has_inspector_signature'] = $hasInspector;
            $recordArray['has_coordinator_signature'] = $hasCoordinator;

            return $this->successResponse($recordArray, 'Detalle de InspeccionVehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/vehicle-inspections',
        summary: 'Crear nuevo registro de InspeccionVehiculo',
        operationId: 'storeVehicleInspection',
        tags: ['InspeccionVehiculo'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'inspection',
                        type: 'object',
                        required: ['company_uuid', 'vehicle_uuid', 'inspection_date', 'mileage'],
                        properties: [
                            new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '7a8e71fa-6509-41bc-a288-f1f3ad450b79'),
                            new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                            new OA\Property(property: 'inspection_date', type: 'string', format: 'date', example: '2026-05-31'),
                            new OA\Property(property: 'inspector_name', type: 'string', nullable: true, example: 'Carlos Ramírez'),
                            new OA\Property(property: 'mileage', type: 'integer', minimum: 0, example: 45200),
                            new OA\Property(property: 'driver_uuid', type: 'string', format: 'uuid', nullable: true, example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                            new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Inspección general de rutina'),
                            new OA\Property(
                                property: 'results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    required: ['item_uuid'],
                                    properties: [
                                        new OA\Property(property: 'item_uuid', type: 'string', format: 'uuid', example: '124f6db2-7371-43ce-9691-476ef4f94a44'),
                                        new OA\Property(property: 'is_selected', type: 'integer', enum: [0, 1], example: 1),
                                        new OA\Property(property: 'status', type: 'string', enum: ['APROBADO', 'NO_APROBADO'], example: 'APROBADO'),
                                        new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'El sistema de frenos se encuentra en buen estado'),
                                    ]
                                )
                            ),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreVehicleInspectionRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $record = $this->inspectionService->createVehicleInspection($validated['inspection'] ?? $validated);

            return $this->successResponse($record, 'Registro de InspeccionVehiculo creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/vehicle-inspections/{uuid}',
        summary: 'Actualizar registro de InspeccionVehiculo por UUID',
        operationId: 'updateVehicleInspection',
        tags: ['InspeccionVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'inspection',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                            new OA\Property(property: 'inspection_date', type: 'string', format: 'date', example: '2026-05-31'),
                            new OA\Property(property: 'inspector_name', type: 'string', nullable: true, example: 'Carlos Ramírez S.'),
                            new OA\Property(property: 'mileage', type: 'integer', minimum: 0, example: 45500),
                            new OA\Property(property: 'driver_uuid', type: 'string', format: 'uuid', nullable: true, example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                            new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Inspección actualizada tras correcciones en el taller'),
                            new OA\Property(
                                property: 'results',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    required: ['item_uuid'],
                                    properties: [
                                        new OA\Property(property: 'item_uuid', type: 'string', format: 'uuid', example: '124f6db2-7371-43ce-9691-476ef4f94a44'),
                                        new OA\Property(property: 'is_selected', type: 'integer', enum: [0, 1], example: 1),
                                        new OA\Property(property: 'status', type: 'string', enum: ['APROBADO', 'NO_APROBADO'], example: 'APROBADO'),
                                        new OA\Property(property: 'observations', type: 'string', nullable: true, example: 'Llantas traseras alineadas y balanceadas correctamente'),
                                    ]
                                )
                            ),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateVehicleInspectionRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de InspeccionVehiculo que desea actualizar no existe.', 404);
            }
            $validated = $request->validated();
            $updated = $this->inspectionService->updateVehicleInspection($uuid, $validated['inspection'] ?? $validated);

            return $this->successResponse($updated, 'Registro de InspeccionVehiculo actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/vehicle-inspections/{uuid}',
        summary: 'Eliminar registro de InspeccionVehiculo por UUID',
        operationId: 'destroyVehicleInspection',
        tags: ['InspeccionVehiculo'],
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
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de InspeccionVehiculo que desea eliminar no existe.', 404);
            }
            $this->inspectionService->deleteVehicleInspection($uuid);

            return $this->successResponse(null, 'Registro de InspeccionVehiculo eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-inspections/{uuid}/pdf',
        summary: 'Descargar PDF de Inspección de Vehículo',
        operationId: 'downloadPdfVehicleInspection',
        tags: ['InspeccionVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF descargado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function downloadPdf(string $uuid)
    {
        try {
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);

            if (! $record) {
                return $this->errorResponse('Inspección no encontrada.', 404);
            }

            // Get logo and firma
            $pdfService = app(PdfService::class);
            $company = $record->company ?? ($record->vehicle->company ?? null);
            $logoModel = $company ? ($company->getFirstMedia('logos') ?? $company->logo ?? null) : null;

            // Fetch inspector-specific signature
            $inspectorSignature = Signature::query()->where('entity_type', 'vehicle_inspection_inspector')
                ->where('entity_id', $record->id)
                ->latest()
                ->first();

            // Fetch coordinator-specific signature (support legacy 'vehicle_inspection' for backward compatibility)
            $coordinatorSignature = Signature::query()->where('entity_type', 'vehicle_inspection_coordinator')
                ->where('entity_id', $record->id)
                ->latest()
                ->first();

            if (! $coordinatorSignature) {
                $coordinatorSignature = Signature::query()->where('entity_type', 'vehicle_inspection')
                    ->where('entity_id', $record->id)
                    ->latest()
                    ->first();
            }

            $base64Images = $pdfService->getBase64Parallel([
                'logo' => $logoModel,
                'firma' => $inspectorSignature,      // null si aún no firmó el inspector
                'firma_coordinador' => $coordinatorSignature,
            ]);

            $data = [
                'logo' => $base64Images['logo'],
                'firma' => $base64Images['firma'],
                'firma_coordinador' => $base64Images['firma_coordinador'],
            ];

            // Agrupar los resultados por categoría
            $groupedResults = [];
            foreach ($record->inspectionResults as $result) {
                $category = $result->item->category ?? 'General';
                if (! isset($groupedResults[$category])) {
                    $groupedResults[$category] = [];
                }
                $groupedResults[$category][] = $result;
            }

            // Distribuir en 2 columnas balanceando la altura (Masonry layout)
            $col1 = [];
            $col2 = [];
            $count1 = 0;
            $count2 = 0;
            foreach ($groupedResults as $cat => $results) {
                // Sumamos los items + 2 para estimar la altura del título de la categoría
                $itemsCount = count($results) + 2;
                if ($count1 <= $count2) {
                    $col1[] = $cat;
                    $count1 += $itemsCount;
                } else {
                    $col2[] = $cat;
                    $count2 += $itemsCount;
                }
            }
            $chunks = [$col1, $col2];

            $pdf = Pdf::loadView('pdf.vehicle-inspection', [
                'record' => $record,
                'groupedResults' => $groupedResults,
                'chunks' => $chunks,
                'data' => $data,
            ])->setPaper('a4', 'portrait');

            return $pdf->download('inspeccion-preoperacional-'.$record->uuid.'.pdf');

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/vehicle-inspections/{uuid}/generate-sign-url',
        summary: 'Generar enlace público temporal firmado para firma digital de inspección',
        operationId: 'generateSignUrlVehicleInspection',
        tags: ['InspeccionVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Enlace público para firma generado con éxito.'),
            new OA\Response(response: 404, description: 'Inspección no encontrada.'),
        ]
    )]
    public function generateSignUrl(string $uuid): JsonResponse
    {
        try {
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Inspección no encontrada.', 404);
            }

            // Generar la URL firmada para la API (usando GET showPublic)
            $apiUrl = URL::temporarySignedRoute(
                'api.v1.public.vehicle-inspections.show',
                now()->addHour(),
                ['uuid' => $uuid],
                false
            );

            // Extraer la firma y expiración del URL generado
            $parsedUrl = parse_url($apiUrl);
            parse_str($parsedUrl['query'] ?? '', $queryParameters);

            // Obtener el base URL de la app frontend (usualmente configurada en env o relativa)
            $frontendBaseUrl = config('app.frontend_url') ?? url('/');

            // Construir la URL del frontend (compatible con HashHistory)
            $publicLink = rtrim($frontendBaseUrl, '/').'/#/firma-documentos/'.$uuid.'?'.http_build_query($queryParameters);

            return $this->successResponse([
                'url' => $publicLink,
                'expires_at' => now()->addHour()->toIso8601String(),
            ], 'Enlace público para firma generado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/public/vehicle-inspections/{uuid}',
        summary: 'Obtener detalles públicos de la inspección para firma (Signed URL)',
        operationId: 'showPublicVehicleInspection',
        tags: ['InspeccionVehiculo'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string'), description: 'Firma de URL firmado'),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), description: 'Timestamp de expiración'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalles públicos de la inspección recuperados.'),
            new OA\Response(response: 404, description: 'Inspección no encontrada.'),
        ]
    )]
    public function showPublic(string $uuid): JsonResponse
    {
        try {
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Inspección no encontrada.', 404);
            }

            $hasInspector = Signature::query()->where('entity_type', 'vehicle_inspection_inspector')->where('entity_id', $record->id)->exists();
            $hasCoordinator = Signature::query()->where('entity_type', 'vehicle_inspection_coordinator')->where('entity_id', $record->id)->exists();
            if (! $hasCoordinator) {
                $hasCoordinator = Signature::query()->where('entity_type', 'vehicle_inspection')->where('entity_id', $record->id)->exists();
            }

            // Retornamos un resumen seguro y básico para la vista pública
            $data = [
                'uuid' => $record->uuid,
                'id' => $record->id,
                'inspection_date' => $record->inspection_date,
                'inspector_name' => $record->inspector_name,
                'mileage' => $record->mileage,
                'vehicle_license_plate' => $record->vehicle->vehicle_license_plate ?? 'N/A',
                'driver_name' => $record->driver ? trim(($record->driver->first_name ?? '').' '.($record->driver->last_name ?? '')) : 'N/A',
                'has_inspector_signature' => $hasInspector,
                'has_coordinator_signature' => $hasCoordinator,
            ];

            return $this->successResponse($data, 'Detalles públicos de la inspección recuperados.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/public/vehicle-inspections/{uuid}',
        summary: 'Firmar inspección de vehículo públicamente (Signed URL)',
        operationId: 'signPublicVehicleInspection',
        tags: ['InspeccionVehiculo'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, schema: new OA\Schema(type: 'string'), description: 'Firma de URL firmado'),
            new OA\Parameter(name: 'expires', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), description: 'Timestamp de expiración'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['signature'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', enum: ['inspector', 'coordinator'], default: 'coordinator', nullable: true),
                    new OA\Property(property: 'signature', type: 'string', description: 'Firma en formato base64 (data:image/png;base64,...)', maxLength: 500000),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Inspección firmada públicamente con éxito.'),
            new OA\Response(response: 400, description: 'Datos de firma inválidos.'),
            new OA\Response(response: 404, description: 'Inspección no encontrada.'),
        ]
    )]
    public function signPublic(Request $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->inspectionService->getVehicleInspectionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Inspección no encontrada.', 404);
            }

            $request->validate([
                'role' => 'nullable|string|in:inspector,coordinator',
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

            $role = $request->input('role', 'coordinator');
            $entityType = $role === 'inspector' ? 'vehicle_inspection_inspector' : 'vehicle_inspection_coordinator';

            // Guardar firma utilizando SignatureService
            // company_uuid se resuelve desde el registro (ruta pública, sin usuario autenticado)
            $companyUuid = $record->company_uuid
                ?? $record->vehicle?->company_uuid
                ?? $record->company?->uuid;

            $signatureService = app(SignatureService::class);
            $signature = $signatureService->store([
                'entity_type' => $entityType,
                'entity_id' => $record->id,
                'company_uuid' => $companyUuid,
                'signature' => $request->input('signature'),
            ]);

            return $this->successResponse($signature, 'Inspección firmada públicamente con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
