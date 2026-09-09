<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\InspectionResult\StoreInspectionResultRequest;
use App\Http\Requests\Fleet\InspectionResult\UpdateInspectionResultRequest;
use App\Services\Fleet\InspectionResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ResultadoInspeccion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class InspectionResultController extends Controller
{
    public function __construct(
        private readonly InspectionResultService $resultService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/inspection-results',
        summary: 'Consultar listado paginado de ResultadoInspeccion',
        operationId: 'listInspectionResults',
        tags: ['ResultadoInspeccion'],
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
            $data = $this->resultService->getAllInspectionResultsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de ResultadoInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/inspection-results/list',
        summary: 'Consultar listado completo (sin paginación) de ResultadoInspeccion',
        operationId: 'getAllInspectionResults',
        tags: ['ResultadoInspeccion'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->resultService->getAllInspectionResults();

            return $this->successResponse($data, 'Listado completo de ResultadoInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/inspection-results/{inspectionUuid}/{itemUuid}',
        summary: 'Obtener detalle de ResultadoInspeccion por Clave Compuesta',
        operationId: 'showInspectionResult',
        tags: ['ResultadoInspeccion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inspectionUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Registro encontrado.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function show(string $inspectionUuid, string $itemUuid): JsonResponse
    {
        try {
            $record = $this->resultService->getInspectionResultByCompoundKey($inspectionUuid, $itemUuid);
            if (! $record) {
                return $this->errorResponse('El registro de ResultadoInspeccion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ResultadoInspeccion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/inspection-results',
        summary: 'Crear nuevo registro de ResultadoInspeccion',
        operationId: 'storeInspectionResult',
        tags: ['ResultadoInspeccion'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreInspectionResultRequest $request): JsonResponse
    {
        try {
            $record = $this->resultService->createInspectionResult($request->validated());

            return $this->successResponse($record, 'Registro de ResultadoInspeccion creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/inspection-results/{inspectionUuid}/{itemUuid}',
        summary: 'Actualizar registro de ResultadoInspeccion por Clave Compuesta',
        operationId: 'updateInspectionResult',
        tags: ['ResultadoInspeccion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inspectionUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateInspectionResultRequest $request, string $inspectionUuid, string $itemUuid): JsonResponse
    {
        try {
            $record = $this->resultService->getInspectionResultByCompoundKey($inspectionUuid, $itemUuid);
            if (! $record) {
                return $this->errorResponse('El registro de ResultadoInspeccion que desea actualizar no existe.', 404);
            }
            $updated = $this->resultService->updateInspectionResult($inspectionUuid, $itemUuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ResultadoInspeccion actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/inspection-results/{inspectionUuid}/{itemUuid}',
        summary: 'Eliminar registro de ResultadoInspeccion por Clave Compuesta',
        operationId: 'destroyInspectionResult',
        tags: ['ResultadoInspeccion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inspectionUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemUuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function destroy(string $inspectionUuid, string $itemUuid): JsonResponse
    {
        try {
            $record = $this->resultService->getInspectionResultByCompoundKey($inspectionUuid, $itemUuid);
            if (! $record) {
                return $this->errorResponse('El registro de ResultadoInspeccion que desea eliminar no existe.', 404);
            }
            $this->resultService->deleteInspectionResult($inspectionUuid, $itemUuid);

            return $this->successResponse(null, 'Registro de ResultadoInspeccion eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
