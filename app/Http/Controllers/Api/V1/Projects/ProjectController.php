<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\Project\StoreProjectRequest;
use App\Http\Requests\Projects\Project\UpdateProjectRequest;
use App\Services\Projects\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de proyectos y sus asignaciones
 * de conductores y vehículos.
 *
 * @author   Darwin Montes
 * @version  1.0.0
 * @since    1.0.0
 * @created  2026-09-01
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService
    ) {}

    #[OA\Get(
        path: '/api/v1/projects',
        summary: 'Consultar listado paginado de proyectos',
        operationId: 'listProjects',
        tags: ['Proyectos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'third_party_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrar proyectos a los que pertenece un conductor'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $thirdPartyUuid = $request->query('third_party_uuid');

            $data = $this->projectService->getProjectsWithPagination($perPage, $page, $search, $companyUuid, $thirdPartyUuid);

            return $this->successResponse($data, 'Listado paginado de proyectos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/projects/list',
        summary: 'Obtener catálogo de proyectos',
        operationId: 'getAllProjects',
        tags: ['Proyectos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'company_uuid', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid');
            $data = $this->projectService->getAllProjects($companyUuid);

            return $this->successResponse($data, 'Catálogo de proyectos recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/projects/{uuid}',
        summary: 'Obtener detalle de proyecto por UUID',
        operationId: 'showProject',
        tags: ['Proyectos'],
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
            $record = $this->projectService->getProjectByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El proyecto solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de proyecto recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/projects',
        summary: 'Crear nuevo proyecto con conductores y vehículos',
        operationId: 'storeProject',
        tags: ['Proyectos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['company_uuid', 'project_name', 'start_date', 'completion_date'],
                properties: [
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'project_name', type: 'string'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'completion_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'project_value', type: 'number', nullable: true),
                    new OA\Property(property: 'purchase_order', type: 'string', nullable: true),
                    new OA\Property(property: 'third_parties', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), description: 'UUIDs de conductores a asignar'),
                    new OA\Property(property: 'vehicles', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), description: 'UUIDs de vehículos a asignar'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $record = $this->projectService->createProject($data);

            return $this->successResponse($record, 'Proyecto creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/projects/{uuid}',
        summary: 'Actualizar proyecto y sus asignaciones por UUID',
        operationId: 'updateProject',
        tags: ['Proyectos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'company_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'project_name', type: 'string'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'completion_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'project_value', type: 'number', nullable: true),
                    new OA\Property(property: 'purchase_order', type: 'string', nullable: true),
                    new OA\Property(property: 'third_parties', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), description: 'UUIDs de conductores a asignar'),
                    new OA\Property(property: 'vehicles', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), description: 'UUIDs de vehículos a asignar'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateProjectRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->projectService->getProjectByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El proyecto que desea actualizar no existe.', 404);
            }

            $data = $request->validated();
            $updated = $this->projectService->updateProject($uuid, $data);

            return $this->successResponse($updated, 'Proyecto actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/projects/{uuid}',
        summary: 'Eliminar proyecto por UUID',
        operationId: 'destroyProject',
        tags: ['Proyectos'],
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
            $record = $this->projectService->getProjectByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El proyecto que desea eliminar no existe.', 404);
            }
            $this->projectService->deleteProject($uuid);

            return $this->successResponse(null, 'Proyecto eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}