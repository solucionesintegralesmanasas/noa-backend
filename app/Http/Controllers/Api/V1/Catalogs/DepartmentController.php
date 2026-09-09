<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\Department\StoreDepartmentRequest;
use App\Http\Requests\Catalogs\Department\UpdateDepartmentRequest;
use App\Services\Catalogs\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión integral y exposición de endpoints de Departamento.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService
    ) {}

    #[OA\Get(
        path: '/api/v1/catalogs/departments',
        summary: 'Consultar listado paginado de Departamento',
        operationId: 'listDepartments',
        tags: ['Departamento'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
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
            $data = $this->departmentService->getAllDepartmentsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado paginado de Departamento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/departments/list',
        summary: 'Obtener catálogo de Departamento',
        operationId: 'getAllDepartments',
        tags: ['Departamento'],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->departmentService->getAllDepartments();

            return $this->successResponse($data, 'Catálogo de Departamento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/catalogs/departments/{uuid}',
        summary: 'Obtener detalle de Departamento por UUID',
        operationId: 'showDepartment',
        tags: ['Departamento'],
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
            $record = $this->departmentService->getDepartmentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Departamento solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalle de Departamento recuperado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/catalogs/departments',
        summary: 'Crear nuevo registro de Departamento',
        operationId: 'storeDepartment',
        tags: ['Departamento'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado con éxito.'),
        ]
    )]
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        try {
            $record = $this->departmentService->createDepartment($request->validated());

            return $this->successResponse($record, 'Registro de Departamento creado con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/catalogs/departments/{uuid}',
        summary: 'Actualizar registro de Departamento por UUID',
        operationId: 'updateDepartment',
        tags: ['Departamento'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
        ]
    )]
    public function update(UpdateDepartmentRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->departmentService->getDepartmentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Departamento que desea actualizar no existe.', 404);
            }
            $updated = $this->departmentService->updateDepartment($uuid, $request->validated());

            return $this->successResponse($updated, 'Registro de Departamento actualizado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/catalogs/departments/{uuid}',
        summary: 'Eliminar registro de Departamento por UUID',
        operationId: 'destroyDepartment',
        tags: ['Departamento'],
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
            $record = $this->departmentService->getDepartmentByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de Departamento que desea eliminar no existe.', 404);
            }
            $this->departmentService->deleteDepartment($uuid);

            return $this->successResponse(null, 'Registro de Departamento eliminado con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
