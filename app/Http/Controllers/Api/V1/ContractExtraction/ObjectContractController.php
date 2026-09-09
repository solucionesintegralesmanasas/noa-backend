<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ContractExtraction;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractExtraction\ObjectContract\StoreObjectContractRequest;
use App\Http\Requests\ContractExtraction\ObjectContract\UpdateObjectContractRequest;
use App\Services\ContractExtraction\ObjectContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Objetos de Contratos.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ObjectContractController extends Controller
{
    public function __construct(
        private readonly ObjectContractService $objectContractService
    ) {}

    #[OA\Get(
        path: '/api/v1/contract-extract/objects-contracts',
        summary: 'Consultar listado paginado de Objetos de Contratos',
        operationId: 'listObjectContracts',
        tags: ['ObjetoContrato'],
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
            $data = $this->objectContractService->getAllObjectContractsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Objetos de Contratos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/objects-contracts/list',
        summary: 'Obtener catálogo de Objetos de Contratos',
        operationId: 'getAllObjectContracts',
        tags: ['ObjetoContrato'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->objectContractService->getAllObjectContracts();

            return $this->successResponse($data, 'Catálogo de Objetos de Contratos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/contract-extract/objects-contracts/{uuid}',
        summary: 'Obtener detalle de Objeto de Contrato por UUID',
        operationId: 'showObjectContract',
        tags: ['ObjetoContrato'],
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
            $record = $this->objectContractService->getObjectContractByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Objeto de Contrato solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Objeto de Contrato recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/contract-extract/objects-contracts',
        summary: 'Crear nuevo registro de Objeto de Contrato',
        operationId: 'storeObjectContract',
        tags: ['ObjetoContrato'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'description'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Prestación de Servicios de Transporte Escolar'),
                    new OA\Property(property: 'description', type: 'string', example: 'Contrato destinado a movilizar estudiantes del sector público rural.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreObjectContractRequest $request): JsonResponse
    {
        try {
            $record = $this->objectContractService->createObjectContract($request->validated());

            return $this->successResponse($record, 'Registro de Objeto de Contrato creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/contract-extract/objects-contracts/{uuid}',
        summary: 'Actualizar registro de Objeto de Contrato por UUID',
        operationId: 'updateObjectContract',
        tags: ['ObjetoContrato'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Prestación de Servicios de Transporte Escolar - Modificado'),
                    new OA\Property(property: 'description', type: 'string', example: 'Contrato destinado a movilizar estudiantes del sector público rural y urbano.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateObjectContractRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->objectContractService->getObjectContractByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Objeto de Contrato que desea actualizar no existe.', 404);
            }
            $updated = $this->objectContractService->updateObjectContract($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Objeto de Contrato actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/contract-extract/objects-contracts/{uuid}',
        summary: 'Eliminar registro de Objeto de Contrato por UUID',
        operationId: 'destroyObjectContract',
        tags: ['ObjetoContrato'],
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
            $record = $this->objectContractService->getObjectContractByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Objeto de Contrato que desea eliminar no existe.', 404);
            }
            $this->objectContractService->deleteObjectContract($uuid);

            return $this->successResponse(null, 'Registro de Objeto de Contrato eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
