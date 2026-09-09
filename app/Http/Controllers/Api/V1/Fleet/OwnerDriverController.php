<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\OwnerDriver\StoreOwnerDriverRequest;
use App\Http\Requests\Fleet\OwnerDriver\UpdateOwnerDriverRequest;
use App\Services\Fleet\OwnerDriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de ConductorPropietario.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class OwnerDriverController extends Controller
{
    public function __construct(
        private readonly OwnerDriverService $ownerDriverService
    ) {}

    #[OA\Get(
        path: '/api/v1/fleet-management/owner-drivers',
        summary: 'Consultar listado paginado de ConductorPropietario',
        operationId: 'listOwnerDrivers',
        tags: ['ConductorPropietario'],
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
            $data = $this->ownerDriverService->getAllOwnerDriversWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de ConductorPropietario recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/owner-drivers/list',
        summary: 'Obtener catálogo de ConductorPropietario',
        operationId: 'getAllOwnerDrivers',
        tags: ['ConductorPropietario'],
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
            $data = $this->ownerDriverService->getAllOwnerDrivers($companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Catálogo de ConductorPropietario recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/fleet-management/owner-drivers/{uuid}',
        summary: 'Obtener detalle de ConductorPropietario por UUID',
        operationId: 'showOwnerDriver',
        tags: ['ConductorPropietario'],
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
            $record = $this->ownerDriverService->getOwnerDriverByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConductorPropietario solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de ConductorPropietario recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/fleet-management/owner-drivers',
        summary: 'Crear nuevo registro de ConductorPropietario',
        operationId: 'storeOwnerDriver',
        tags: ['ConductorPropietario'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreOwnerDriverRequest $request): JsonResponse
    {
        try {
            $record = $this->ownerDriverService->createOwnerDriver($request->validated());

            return $this->successResponse($record, 'Registro de ConductorPropietario creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/fleet-management/owner-drivers/{uuid}',
        summary: 'Actualizar registro de ConductorPropietario por UUID',
        operationId: 'updateOwnerDriver',
        tags: ['ConductorPropietario'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateOwnerDriverRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->ownerDriverService->getOwnerDriverByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConductorPropietario que desea actualizar no existe.', 404);
            }
            $updated = $this->ownerDriverService->updateOwnerDriver($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de ConductorPropietario actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/fleet-management/owner-drivers/{uuid}',
        summary: 'Eliminar registro de ConductorPropietario por UUID',
        operationId: 'destroyOwnerDriver',
        tags: ['ConductorPropietario'],
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
            $record = $this->ownerDriverService->getOwnerDriverByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de ConductorPropietario que desea eliminar no existe.', 404);
            }
            $this->ownerDriverService->deleteOwnerDriver($uuid);

            return $this->successResponse(null, 'Registro de ConductorPropietario eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
