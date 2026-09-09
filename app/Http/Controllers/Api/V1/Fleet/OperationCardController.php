<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\OperationCard\StoreOperationCardRequest;
use App\Http\Requests\Fleet\OperationCard\UpdateOperationCardRequest;
use App\Services\Fleet\OperationCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de TarjetaOperacion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class OperationCardController extends Controller
{
    public function __construct(
        private readonly OperationCardService $operationCardService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/operation-cards',
        summary: 'Consultar listado paginado de TarjetaOperacion',
        operationId: 'listOperationCards',
        tags: ['TarjetaOperacion'],
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
            $data = $this->operationCardService->getAllOperationCardsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de TarjetaOperacion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/operation-cards/list',
        summary: 'Obtener catálogo de TarjetaOperacion',
        operationId: 'getAllOperationCards',
        tags: ['TarjetaOperacion'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->operationCardService->getAllOperationCards($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de TarjetaOperacion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/operation-cards/{uuid}',
        summary: 'Obtener detalle de TarjetaOperacion por UUID',
        operationId: 'showOperationCard',
        tags: ['TarjetaOperacion'],
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
            $record = $this->operationCardService->getOperationCardByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TarjetaOperacion solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de TarjetaOperacion recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/operation-cards',
        summary: 'Crear nuevo registro de TarjetaOperacion',
        operationId: 'storeOperationCard',
        tags: ['TarjetaOperacion'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreOperationCardRequest $request): JsonResponse
    {
        try {
            $record = $this->operationCardService->createOperationCard($request->validated());

            return $this->successResponse($record, 'Registro de TarjetaOperacion creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/operation-cards/{uuid}',
        summary: 'Actualizar registro de TarjetaOperacion por UUID',
        operationId: 'updateOperationCard',
        tags: ['TarjetaOperacion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateOperationCardRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->operationCardService->getOperationCardByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TarjetaOperacion que desea actualizar no existe.', 404);
            }
            $updated = $this->operationCardService->updateOperationCard($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de TarjetaOperacion actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/operation-cards/{uuid}',
        summary: 'Eliminar registro de TarjetaOperacion por UUID',
        operationId: 'destroyOperationCard',
        tags: ['TarjetaOperacion'],
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
            $record = $this->operationCardService->getOperationCardByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TarjetaOperacion que desea eliminar no existe.', 404);
            }
            $this->operationCardService->deleteOperationCard($uuid);

            return $this->successResponse(null, 'Registro de TarjetaOperacion eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/fleet-management/operation-cards/{uuid}/toggle-status',
        summary: 'Alternar el estado de activación de una TarjetaOperacion',
        operationId: 'toggleOperationCardStatus',
        tags: ['TarjetaOperacion'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado alternado con éxito.'),
            new OA\Response(response: 404, description: 'TarjetaOperacion no encontrada.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->operationCardService->getOperationCardByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de TarjetaOperacion no existe.', 404);
            }
            $updated = $this->operationCardService->toggleOperationCardStatus($uuid);

            return $this->successResponse($updated, 'Estado de la TarjetaOperacion alternado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
