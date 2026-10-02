<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\ServiceProvisionContract\StoreServiceProvisionContractRequest;
use App\Http\Requests\Procedure\ServiceProvisionContract\UpdateServiceProvisionContractRequest;
use App\Services\Procedure\ServiceProvisionContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Contratos de Prestación de Servicios.
 */
class ServiceProvisionContractController extends Controller
{
    public function __construct(
        private readonly ServiceProvisionContractService $serviceProvisionContractService
    ) {}

    #[OA\Get(
        path: '/api/v1/procedure/service-provision-contracts',
        summary: 'Consultar listado paginado de Contratos de Prestación',
        operationId: 'listServiceProvisionContracts',
        tags: ['ContratoPrestación'],
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
            $data = $this->serviceProvisionContractService->getAllWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Contratos de Prestación recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/service-provision-contracts/list',
        summary: 'Obtener catálogo de Contratos de Prestación',
        operationId: 'getAllServiceProvisionContracts',
        tags: ['ContratoPrestación'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->serviceProvisionContractService->getAll();

            return $this->successResponse($data, 'Catálogo de Contratos de Prestación recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/service-provision-contracts/{uuid}',
        summary: 'Obtener detalle de Contrato de Prestación por UUID',
        operationId: 'showServiceProvisionContract',
        tags: ['ContratoPrestación'],
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
            $record = $this->serviceProvisionContractService->getByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contrato de Prestación solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Contrato de Prestación recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/procedure/service-provision-contracts',
        summary: 'Crear nuevo registro de Contrato de Prestación',
        operationId: 'storeServiceProvisionContract',
        tags: ['ContratoPrestación'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreServiceProvisionContractRequest $request): JsonResponse
    {
        try {
            $record = $this->serviceProvisionContractService->create($request->validated());

            return $this->successResponse($record, 'Registro de Contrato de Prestación creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/procedure/service-provision-contracts/{uuid}',
        summary: 'Actualizar registro de Contrato de Prestación por UUID',
        operationId: 'updateServiceProvisionContract',
        tags: ['ContratoPrestación'],
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
    public function update(UpdateServiceProvisionContractRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->serviceProvisionContractService->getByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contrato de Prestación que desea actualizar no existe.', 404);
            }
            $this->serviceProvisionContractService->update($uuid, $request->validated());
            $record = $this->serviceProvisionContractService->getByUuid($uuid);

            return $this->successResponse($record, 'Registro de Contrato de Prestación actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/procedure/service-provision-contracts/{uuid}',
        summary: 'Eliminar registro de Contrato de Prestación por UUID',
        operationId: 'destroyServiceProvisionContract',
        tags: ['ContratoPrestación'],
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
            $record = $this->serviceProvisionContractService->getByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Contrato de Prestación que desea eliminar no existe.', 404);
            }
            $this->serviceProvisionContractService->delete($uuid);

            return $this->successResponse(null, 'Registro de Contrato de Prestación eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}