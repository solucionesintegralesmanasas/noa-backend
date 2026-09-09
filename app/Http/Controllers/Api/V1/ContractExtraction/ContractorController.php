<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ContractExtraction;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractExtraction\Contractor\StoreContractorRequest;
use App\Http\Requests\ContractExtraction\Contractor\UpdateContractorRequest;
use App\Services\ContractExtraction\ContractorService;
use App\Services\ContractExtraction\FuecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Contratistas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ContractorController extends Controller
{
    public function __construct(
        private readonly ContractorService $contractorService,
        private readonly FuecService $fuecService
    ) {}

    #[OA\Get(
        path: '/api/v1/contract-extract/contractors',
        summary: 'Consultar listado paginado de Contratistas',
        operationId: 'listContractors',
        tags: ['Contratista'],
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
            $vehicleUuid = $request->query('vehicle_uuid');
            $data = $this->contractorService->getAllContractorsWithPagination($perPage, $page, $search, $companyUuid, $vehicleUuid);

            return $this->successResponse($data, 'Listado paginado de Contratistas recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/contractors/list',
        summary: 'Obtener catálogo de Contratistas',
        operationId: 'getAllContractors',
        tags: ['Contratista'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->contractorService->getAllContractors();

            return $this->successResponse($data, 'Catálogo de Contratistas recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/contractors/next-consecutive',
        summary: 'Obtener el próximo consecutivo del contrato para previsualización',
        operationId: 'getNextContractConsecutive',
        tags: ['Contratista'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 400, description: 'Parámetros inválidos.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function nextConsecutive(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid');
            if (! $companyUuid) {
                return $this->errorResponse('El parámetro company_uuid es obligatorio.', 400);
            }

            $year = $request->query('year') ? (int) $request->query('year') : null;

            $nextConsecutive = $this->fuecService->getPreviewContractConsecutive($companyUuid, $year);

            return $this->successResponse([
                'next_consecutive' => $nextConsecutive,
                'formatted' => str_pad((string) ($nextConsecutive % 10000), 4, '0', STR_PAD_LEFT),
            ], 'Próximo consecutivo obtenido con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/contractors/{uuid}',
        summary: 'Obtener detalle de Contratista por UUID',
        operationId: 'showContractor',
        tags: ['Contratista'],
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
            $record = $this->contractorService->getContractorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contratista solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Contratista recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/contract-extract/contractors',
        summary: 'Crear nuevo registro de Contratista',
        operationId: 'storeContractor',
        tags: ['Contratista'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreContractorRequest $request): JsonResponse
    {
        try {
            $record = $this->contractorService->createContractor($request->validated());

            return $this->successResponse($record, 'Registro de Contratista creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/contract-extract/contractors/{uuid}',
        summary: 'Actualizar registro de Contratista por UUID',
        operationId: 'updateContractor',
        tags: ['Contratista'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateContractorRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->contractorService->getContractorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contratista que desea actualizar no existe.', 404);
            }
            $updated = $this->contractorService->updateContractor($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Contratista actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/contract-extract/contractors/{uuid}',
        summary: 'Eliminar registro de Contratista por UUID',
        operationId: 'destroyContractor',
        tags: ['Contratista'],
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
            $record = $this->contractorService->getContractorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contratista que desea eliminar no existe.', 404);
            }
            $this->contractorService->deleteContractor($uuid);

            return $this->successResponse(null, 'Registro de Contratista eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/contract-extract/contractors/{uuid}/toggle-status',
        summary: 'Alternar el estado de activación de un Contratista',
        operationId: 'toggleContractorStatus',
        tags: ['Contratista'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado alternado con éxito.'),
            new OA\Response(response: 404, description: 'Contratista no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->contractorService->getContractorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contratista no existe.', 404);
            }
            $updated = $this->contractorService->toggleContractorStatus($uuid);

            return $this->successResponse($updated, 'Estado del Contratista alternado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
