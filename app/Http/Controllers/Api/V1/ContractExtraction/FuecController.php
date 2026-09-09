<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ContractExtraction;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractExtraction\Fuec\StoreFuecRequest;
use App\Http\Requests\ContractExtraction\Fuec\UpdateFuecRequest;
use App\Services\ContractExtraction\FuecService;
use App\Services\Pdf\PdfService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de FUEC.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FuecController extends Controller
{
    public function __construct(
        private readonly FuecService $fuecService
    ) {}

    #[OA\Get(
        path: '/api/v1/contract-extract/fuecs',
        summary: 'Consultar listado paginado de FUECs',
        operationId: 'listFuecs',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
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
            $companyUuid = $request->query('company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid');

            $data = $this->fuecService->getAllFuecsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de FUECs recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/fuecs/list',
        summary: 'Obtener catálogo de FUECs',
        operationId: 'getAllFuecs',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid');
            $data = $this->fuecService->getAllFuecs($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de FUECs recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/fuecs/{uuid}',
        summary: 'Obtener detalle de FUEC por UUID',
        operationId: 'showFuec',
        tags: ['FUEC'],
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
            $record = $this->fuecService->getFuecByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de FUEC solicitado no existe.', 404);
            }

            $record->load([
                'company.media',
                'contractor',
                'vehicle.brand',
                'vehicle.vehicleClass',
                'vehicle.operationCards',
                'vehicle.businessCollaborationAgreements',
                'objectsContract',
                'mainConductor.driverLicenses',
                'secondaryConductor.driverLicenses',
                'tertiaryConductor.driverLicenses',
                'passengers.typeOfDocument',
            ]);

            return $this->successResponse($record, 'Detalle de FUEC recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/public/fuecs/{code}',
        summary: 'Validar autenticidad de FUEC públicamente',
        operationId: 'validatePublicFuec',
        tags: ['FUEC'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, description: 'UUID o Código de Verificación', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro válido encontrado.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function validatePublic(string $code): JsonResponse
    {
        try {
            $record = $this->fuecService->validatePublicFuec($code);

            if (! $record) {
                return $this->errorResponse('El FUEC solicitado no existe o el código es inválido.', 404);
            }

            return $this->successResponse($record, 'Detalle público de FUEC validado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/contract-extract/fuecs',
        summary: 'Crear nuevo registro de FUEC',
        operationId: 'storeFuec',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['issue_date', 'request_number', 'number_fuec', 'contract_number_display', 'company_uuid', 'contractor_uuid', 'vehicle_uuid', 'effective_date', 'expiration_date', 'origin_route', 'destination_route', 'object_contract_uuid', 'main_conductor_uuid', 'verification_code', 'contractor'],
                properties: [
                    new OA\Property(property: 'issue_date', type: 'string', format: 'date', example: '2026-05-31'),
                    new OA\Property(property: 'request_number', type: 'string', maxLength: 50, example: 'REQ-FUEC-2026-001'),
                    new OA\Property(property: 'number_fuec', type: 'string', maxLength: 50, example: 'FUEC-4589320-2026'),
                    new OA\Property(property: 'contract_number_display', type: 'string', maxLength: 50, example: 'CONTRATO-045'),
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid', example: '7a8e71fa-6509-41bc-a288-f1f3ad450b79'),
                    new OA\Property(property: 'contractor_uuid', type: 'string', format: 'uuid', example: '5d8e7bfa-4509-4bc1-b288-e2f3bc450b81'),
                    new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                    new OA\Property(property: 'effective_date', type: 'string', format: 'date', example: '2026-06-01'),
                    new OA\Property(property: 'expiration_date', type: 'string', format: 'date', example: '2026-06-15'),
                    new OA\Property(property: 'origin_route', type: 'string', maxLength: 255, example: 'Bogotá D.C., Colombia'),
                    new OA\Property(property: 'destination_route', type: 'string', maxLength: 255, example: 'Medellín, Antioquia, Colombia'),
                    new OA\Property(property: 'object_contract_uuid', type: 'string', format: 'uuid', example: '2ea4d6d4-285e-48a9-8c75-0a6d13d3f338'),
                    new OA\Property(property: 'main_conductor_uuid', type: 'string', format: 'uuid', example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                    new OA\Property(property: 'secondary_conductor_uuid', type: 'string', format: 'uuid', nullable: true, example: 'a9b4b0cc-cd64-4d28-9b2a-085e5bd4b6c7'),
                    new OA\Property(property: 'tertiary_conductor_uuid', type: 'string', format: 'uuid', nullable: true, example: 'b8b4b0cc-cd64-4d28-9b2a-085e5bd4b6c8'),
                    new OA\Property(property: 'verification_code', type: 'string', maxLength: 100, example: 'VERIFY-CODE-98745A'),
                    new OA\Property(property: 'status', type: 'string', enum: ['ACTIVO', 'CERRADO', 'ANULADO'], example: 'ACTIVO'),
                    new OA\Property(
                        property: 'passengers',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['uuid', 'type_of_document_uuid', 'document_number', 'first_and_last_name'],
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
                                new OA\Property(property: 'type_of_document_uuid', type: 'string', format: 'uuid', example: 'b0fa7341-3d23-41bb-9279-b1d683a123f1'),
                                new OA\Property(property: 'document_number', type: 'string', maxLength: 50, example: '1020304050'),
                                new OA\Property(property: 'first_and_last_name', type: 'string', maxLength: 255, example: 'Juan Pérez'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'contractor',
                        type: 'object',
                        required: ['document_type_uuid', 'document_number', 'company_name', 'address', 'telephone', 'contract_number', 'contracting_party_city', 'vehicle_uuid', 'responsible_name', 'responsible_document', 'responsible_phone', 'responsible_address'],
                        properties: [
                            new OA\Property(property: 'uuid', type: 'string', format: 'uuid', nullable: true, example: '5d8e7bfa-4509-4bc1-b288-e2f3bc450b81'),
                            new OA\Property(property: 'document_type_uuid', type: 'string', format: 'uuid', example: 'b0fa7341-3d23-41bb-9279-b1d683a123f1'),
                            new OA\Property(property: 'document_number', type: 'string', example: '900.123.456-7'),
                            new OA\Property(property: 'company_name', type: 'string', example: 'Transportes del Norte S.A.S.'),
                            new OA\Property(property: 'address', type: 'string', example: 'Calle 100 #15-30, Bogotá'),
                            new OA\Property(property: 'telephone', type: 'string', example: '6017458963'),
                            new OA\Property(property: 'contract_number', type: 'string', example: 'CON-2026-045'),
                            new OA\Property(property: 'contracting_party_city', type: 'string', example: 'Bogotá D.C.'),
                            new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                            new OA\Property(property: 'responsible_name', type: 'string', example: 'Carlos Andrés Gómez'),
                            new OA\Property(property: 'responsible_document', type: 'string', example: '79.854.120'),
                            new OA\Property(property: 'responsible_phone', type: 'string', example: '3157896245'),
                            new OA\Property(property: 'responsible_address', type: 'string', example: 'Carrera 7 #45-10, Bogotá'),
                            new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreFuecRequest $request): JsonResponse
    {
        try {
            $record = $this->fuecService->createFuec($request->validated());

            return $this->successResponse($record, 'Registro de FUEC creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/contract-extract/fuecs/{uuid}',
        summary: 'Actualizar registro de FUEC por UUID',
        operationId: 'updateFuec',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'issue_date', type: 'string', format: 'date', example: '2026-05-31'),
                    new OA\Property(property: 'request_number', type: 'string', maxLength: 50, example: 'REQ-FUEC-2026-001B'),
                    new OA\Property(property: 'number_fuec', type: 'string', maxLength: 50, example: 'FUEC-4589320-2026B'),
                    new OA\Property(property: 'contract_number_display', type: 'string', maxLength: 50, example: 'CONTRATO-045B'),
                    new OA\Property(property: 'contractor_uuid', type: 'string', format: 'uuid', example: '5d8e7bfa-4509-4bc1-b288-e2f3bc450b81'),
                    new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                    new OA\Property(property: 'effective_date', type: 'string', format: 'date', example: '2026-06-01'),
                    new OA\Property(property: 'expiration_date', type: 'string', format: 'date', example: '2026-06-20'),
                    new OA\Property(property: 'origin_route', type: 'string', maxLength: 255, example: 'Bogotá D.C. Centro, Colombia'),
                    new OA\Property(property: 'destination_route', type: 'string', maxLength: 255, example: 'Medellín Norte, Antioquia, Colombia'),
                    new OA\Property(property: 'object_contract_uuid', type: 'string', format: 'uuid', example: '2ea4d6d4-285e-48a9-8c75-0a6d13d3f338'),
                    new OA\Property(property: 'main_conductor_uuid', type: 'string', format: 'uuid', example: '1da4d6d4-285e-48a9-8c75-0a6d13d3f337'),
                    new OA\Property(property: 'secondary_conductor_uuid', type: 'string', format: 'uuid', nullable: true, example: 'a9b4b0cc-cd64-4d28-9b2a-085e5bd4b6c7'),
                    new OA\Property(property: 'tertiary_conductor_uuid', type: 'string', format: 'uuid', nullable: true, example: 'b8b4b0cc-cd64-4d28-9b2a-085e5bd4b6c8'),
                    new OA\Property(property: 'verification_code', type: 'string', maxLength: 100, example: 'VERIFY-CODE-98745B'),
                    new OA\Property(property: 'status', type: 'string', enum: ['ACTIVO', 'CERRADO', 'ANULADO'], example: 'ACTIVO'),
                    new OA\Property(
                        property: 'passengers',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['uuid', 'type_of_document_uuid', 'document_number', 'first_and_last_name'],
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
                                new OA\Property(property: 'type_of_document_uuid', type: 'string', format: 'uuid', example: 'b0fa7341-3d23-41bb-9279-b1d683a123f1'),
                                new OA\Property(property: 'document_number', type: 'string', maxLength: 50, example: '1020304050'),
                                new OA\Property(property: 'first_and_last_name', type: 'string', maxLength: 255, example: 'Juan Pérez S.'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'contractor',
                        type: 'object',
                        required: ['uuid', 'document_type_uuid', 'document_number', 'company_name', 'address', 'telephone', 'contract_number', 'contracting_party_city', 'vehicle_uuid', 'responsible_name', 'responsible_document', 'responsible_phone', 'responsible_address'],
                        properties: [
                            new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '5d8e7bfa-4509-4bc1-b288-e2f3bc450b81'),
                            new OA\Property(property: 'document_type_uuid', type: 'string', format: 'uuid', example: 'b0fa7341-3d23-41bb-9279-b1d683a123f1'),
                            new OA\Property(property: 'document_number', type: 'string', example: '900.123.456-7'),
                            new OA\Property(property: 'company_name', type: 'string', example: 'Transportes del Norte S.A.S.'),
                            new OA\Property(property: 'address', type: 'string', example: 'Calle 100 #15-30, Bogotá'),
                            new OA\Property(property: 'telephone', type: 'string', example: '6017458963'),
                            new OA\Property(property: 'contract_number', type: 'string', example: 'CON-2026-045'),
                            new OA\Property(property: 'contracting_party_city', type: 'string', example: 'Bogotá D.C.'),
                            new OA\Property(property: 'vehicle_uuid', type: 'string', format: 'uuid', example: '48b4b0cc-cd64-4d28-9b2a-085e5bd4b6c6'),
                            new OA\Property(property: 'responsible_name', type: 'string', example: 'Carlos Andrés Gómez'),
                            new OA\Property(property: 'responsible_document', type: 'string', example: '79.854.120'),
                            new OA\Property(property: 'responsible_phone', type: 'string', example: '3157896245'),
                            new OA\Property(property: 'responsible_address', type: 'string', example: 'Carrera 7 #45-10, Bogotá'),
                            new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1),
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
    public function update(UpdateFuecRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->fuecService->getFuecByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de FUEC que desea actualizar no existe.', 404);
            }
            $updated = $this->fuecService->updateFuec($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de FUEC actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/contract-extract/fuecs/{uuid}',
        summary: 'Eliminar registro de FUEC por UUID',
        operationId: 'destroyFuec',
        tags: ['FUEC'],
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
            $record = $this->fuecService->getFuecByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de FUEC que desea eliminar no existe.', 404);
            }
            $this->fuecService->deleteFuec($uuid);

            return $this->successResponse(null, 'Registro de FUEC eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/contract-extract/fuecs/{uuid}/assign-vehicle',
        summary: 'Asignar vehículo a contrato y generar número de FUEC',
        operationId: 'assignVehicleToContract',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 400, description: 'Datos inválidos o inconsistentes.'),
            new OA\Response(response: 404, description: 'Contrato o resolución activa no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function assignVehicleToContract(string $uuid): JsonResponse
    {
        try {
            $result = $this->fuecService->assignVehicleToContract($uuid);

            return $this->successResponse($result, 'Vehículo asignado al contrato y consecutivo FUEC generado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e, [
                'uuid' => $uuid,
            ]);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/fuecs/preview-number',
        summary: 'Obtener vista previa del número FUEC de 23 dígitos',
        operationId: 'previewFuecNumber',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'contractor_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'contract_number', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 400, description: 'Datos inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function previewFuecNumber(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid');
            $contractorUuid = $request->query('contractor_uuid');
            $contractNumber = $request->query('contract_number');

            if (! $companyUuid) {
                return $this->errorResponse('El parámetro company_uuid es obligatorio.', 400);
            }

            $previewNumber = $this->fuecService->getPreviewFuecNumber($companyUuid, $contractorUuid, $contractNumber);

            return $this->successResponse([
                'preview_number' => $previewNumber,
            ], 'Vista previa de número FUEC generada con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/fuecs/{uuid}/pdf',
        summary: 'Descargar PDF de FUEC',
        operationId: 'downloadPdfFuec',
        tags: ['FUEC'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF descargado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function downloadPdf(string $uuid, PdfService $pdfService)
    {
        try {
            $fuec = $this->fuecService->getFuecByUuid($uuid);
            if (! $fuec) {
                return $this->errorResponse('El registro de FUEC solicitado no existe.', 404);
            }

            $pdfData = $pdfService->getFuecPdf($fuec->verification_code);
            /** @var PDF $pdf */
            $pdf = $pdfData['pdf'];
            $fileName = $pdfData['file_name'];

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
