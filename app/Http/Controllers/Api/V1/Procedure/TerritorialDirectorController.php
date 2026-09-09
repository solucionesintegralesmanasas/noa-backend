<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\TerritorialDirector\StoreTerritorialDirectorRequest;
use App\Http\Requests\Procedure\TerritorialDirector\UpdateTerritorialDirectorRequest;
use App\Services\Procedure\TerritorialDirectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de DirectorTerritorial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TerritorialDirectorController extends Controller
{
    public function __construct(
        private readonly TerritorialDirectorService $territorialDirectorService
    ) {}

    #[OA\Get(
        path: '/api/v1/procedure/territorial-directors',
        summary: 'Consultar listado paginado de DirectorTerritorial',
        operationId: 'listTerritorialDirectors',
        tags: ['DirectorTerritorial'],
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
            $data = $this->territorialDirectorService->getAllTerritorialDirectorsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de DirectorTerritorial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/territorial-directors/list',
        summary: 'Obtener catálogo de DirectorTerritorial',
        operationId: 'getAllTerritorialDirectors',
        tags: ['DirectorTerritorial'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->territorialDirectorService->getAllTerritorialDirectors();

            return $this->successResponse($data, 'Catálogo de DirectorTerritorial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/territorial-directors/{uuid}',
        summary: 'Obtener detalle de DirectorTerritorial por UUID',
        operationId: 'showTerritorialDirector',
        tags: ['DirectorTerritorial'],
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
            $record = $this->territorialDirectorService->getTerritorialDirectorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DirectorTerritorial solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de DirectorTerritorial recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/procedure/territorial-directors',
        summary: 'Crear nuevo registro de DirectorTerritorial',
        operationId: 'storeTerritorialDirector',
        tags: ['DirectorTerritorial'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreTerritorialDirectorRequest $request): JsonResponse
    {
        try {
            $record = $this->territorialDirectorService->createTerritorialDirector($request->validated());

            return $this->successResponse($record, 'Registro de DirectorTerritorial creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/procedure/territorial-directors/{uuid}',
        summary: 'Actualizar registro de DirectorTerritorial por UUID',
        operationId: 'updateTerritorialDirector',
        tags: ['DirectorTerritorial'],
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
    public function update(UpdateTerritorialDirectorRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->territorialDirectorService->getTerritorialDirectorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DirectorTerritorial que desea actualizar no existe.', 404);
            }
            $updated = $this->territorialDirectorService->updateTerritorialDirector($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de DirectorTerritorial actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/procedure/territorial-directors/{uuid}',
        summary: 'Eliminar registro de DirectorTerritorial por UUID',
        operationId: 'destroyTerritorialDirector',
        tags: ['DirectorTerritorial'],
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
            $record = $this->territorialDirectorService->getTerritorialDirectorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DirectorTerritorial que desea eliminar no existe.', 404);
            }
            $this->territorialDirectorService->deleteTerritorialDirector($uuid);

            return $this->successResponse(null, 'Registro de DirectorTerritorial eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/procedure/territorial-directors/{uuid}/toggle-status',
        summary: 'Alternar el estado de activación de un DirectorTerritorial',
        operationId: 'toggleTerritorialDirectorStatus',
        tags: ['DirectorTerritorial'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado alternado con éxito.'),
            new OA\Response(response: 404, description: 'DirectorTerritorial no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->territorialDirectorService->getTerritorialDirectorByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de DirectorTerritorial no existe.', 404);
            }
            $updated = $this->territorialDirectorService->toggleTerritorialDirectorStatus($uuid);

            return $this->successResponse($updated, 'Estado del DirectorTerritorial alternado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
