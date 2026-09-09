<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procedure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\Procedure\StoreProcedureRequest;
use App\Http\Requests\Procedure\Procedure\UpdateProcedureRequest;
use App\Services\Procedure\ProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Tramite.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ProcedureController extends Controller
{
    public function __construct(
        private readonly ProcedureService $procedureService
    ) {}

    #[OA\Get(
        path: '/api/v1/procedure/procedures',
        summary: 'Consultar listado paginado de Tramite',
        operationId: 'listProcedures',
        tags: ['Tramite'],
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
            $data = $this->procedureService->getAllProceduresWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Tramite recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/procedures/list',
        summary: 'Obtener catálogo de Tramite',
        operationId: 'getAllProcedures',
        tags: ['Tramite'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->procedureService->getAllProcedures();

            return $this->successResponse($data, 'Catálogo de Tramite recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/procedure/procedures/{uuid}',
        summary: 'Obtener detalle de Tramite por UUID',
        operationId: 'showProcedure',
        tags: ['Tramite'],
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
            $record = $this->procedureService->getProcedureByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tramite solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Tramite recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/procedure/procedures',
        summary: 'Crear nuevo registro de Tramite',
        operationId: 'storeProcedure',
        tags: ['Tramite'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreProcedureRequest $request): JsonResponse
    {
        try {
            $record = $this->procedureService->createProcedure($request->validated());

            return $this->successResponse($record, 'Registro de Tramite creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/procedure/procedures/{uuid}',
        summary: 'Actualizar registro de Tramite por UUID',
        operationId: 'updateProcedure',
        tags: ['Tramite'],
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
    public function update(UpdateProcedureRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->procedureService->getProcedureByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tramite que desea actualizar no existe.', 404);
            }
            $updated = $this->procedureService->updateProcedure($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Tramite actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/procedure/procedures/{uuid}',
        summary: 'Eliminar registro de Tramite por UUID',
        operationId: 'destroyProcedure',
        tags: ['Tramite'],
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
            $record = $this->procedureService->getProcedureByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Tramite que desea eliminar no existe.', 404);
            }
            $this->procedureService->deleteProcedure($uuid);

            return $this->successResponse(null, 'Registro de Tramite eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
