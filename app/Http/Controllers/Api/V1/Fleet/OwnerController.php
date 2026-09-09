<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\Owner\StoreOwnerRequest;
use App\Http\Requests\Fleet\Owner\UpdateOwnerRequest;
use App\Services\Fleet\OwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Propietario.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class OwnerController extends Controller
{
    public function __construct(
        private readonly OwnerService $ownerService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/owners',
        summary: 'Consultar listado paginado de Propietario',
        operationId: 'listOwners',
        tags: ['Propietario'],
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
            $thirdPartyUuid = $request->query('third_party_uuid') ?? $request->input('filter.third_party_uuid');
            $data = $this->ownerService->getAllOwnersWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de Propietario recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/owners/list',
        summary: 'Obtener catálogo de Propietario',
        operationId: 'getAllOwners',
        tags: ['Propietario'],
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
            $data = $this->ownerService->getAllOwners($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de Propietario recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/owners/{uuid}',
        summary: 'Obtener detalle de Propietario por UUID',
        operationId: 'showOwner',
        tags: ['Propietario'],
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
            $record = $this->ownerService->getOwnerByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Propietario solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Propietario recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/owners',
        summary: 'Crear nuevo registro de Propietario',
        operationId: 'storeOwner',
        tags: ['Propietario'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreOwnerRequest $request): JsonResponse
    {
        try {
            $record = $this->ownerService->createOwner($request->validated());

            return $this->successResponse($record, 'Registro de Propietario creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/owners/{uuid}',
        summary: 'Actualizar registro de Propietario por UUID',
        operationId: 'updateOwner',
        tags: ['Propietario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateOwnerRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->ownerService->getOwnerByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Propietario que desea actualizar no existe.', 404);
            }
            $updated = $this->ownerService->updateOwner($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Propietario actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/owners/{uuid}',
        summary: 'Eliminar registro de Propietario por UUID',
        operationId: 'destroyOwner',
        tags: ['Propietario'],
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
            $record = $this->ownerService->getOwnerByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Propietario que desea eliminar no existe.', 404);
            }
            $this->ownerService->deleteOwner($uuid);

            return $this->successResponse(null, 'Registro de Propietario eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
