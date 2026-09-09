<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\VehicleDocument\StoreVehicleDocumentRequest;
use App\Http\Requests\Fleet\VehicleDocument\UpdateVehicleDocumentRequest;
use App\Services\Fleet\VehicleDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de DocumentoVehiculo.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class VehicleDocumentController extends Controller
{
    public function __construct(
        private readonly VehicleDocumentService $vehicleDocumentService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-documents',
        summary: 'Consultar listado paginado de DocumentoVehiculo',
        operationId: 'listVehicleDocuments',
        tags: ['DocumentoVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
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
            $documentType = $request->query('document_type') ?? $request->input('filter.document_type');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->vehicleDocumentService->getAllVehicleDocumentsWithPagination($perPage, $page, $search, $companyUuid, $documentType, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de DocumentoVehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-documents/list',
        summary: 'Obtener catálogo de DocumentoVehiculo',
        operationId: 'getAllVehicleDocuments',
        tags: ['DocumentoVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'vehicle_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'third_party_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $vehicleUuid = $request->query('vehicle_uuid') ?? $request->input('filter.vehicle_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->vehicleDocumentService->getAllVehicleDocuments($companyUuid, $vehicleUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de DocumentoVehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/vehicle-documents/{uuid}',
        summary: 'Obtener detalle de DocumentoVehiculo por UUID',
        operationId: 'showVehicleDocument',
        tags: ['DocumentoVehiculo'],
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
            $record = $this->vehicleDocumentService->getVehicleDocumentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DocumentoVehiculo solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de DocumentoVehiculo recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/vehicle-documents',
        summary: 'Crear nuevo registro de DocumentoVehiculo',
        operationId: 'storeVehicleDocument',
        tags: ['DocumentoVehiculo'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreVehicleDocumentRequest $request): JsonResponse
    {
        try {
            $record = $this->vehicleDocumentService->createVehicleDocument($request->validated());

            return $this->successResponse($record, 'Registro de DocumentoVehiculo creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/vehicle-documents/{uuid}',
        summary: 'Actualizar registro de DocumentoVehiculo por UUID',
        operationId: 'updateVehicleDocument',
        tags: ['DocumentoVehiculo'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateVehicleDocumentRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->vehicleDocumentService->getVehicleDocumentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DocumentoVehiculo que desea actualizar no existe.', 404);
            }
            $updated = $this->vehicleDocumentService->updateVehicleDocument($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de DocumentoVehiculo actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/vehicle-documents/{uuid}',
        summary: 'Eliminar registro de DocumentoVehiculo por UUID',
        operationId: 'destroyVehicleDocument',
        tags: ['DocumentoVehiculo'],
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
            $record = $this->vehicleDocumentService->getVehicleDocumentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DocumentoVehiculo que desea eliminar no existe.', 404);
            }
            $this->vehicleDocumentService->deleteVehicleDocument($uuid);

            return $this->successResponse(null, 'Registro de DocumentoVehiculo eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
