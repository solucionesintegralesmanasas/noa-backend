<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\Branch\StoreBranchRequest;
use App\Http\Requests\Administrations\Branch\UpdateBranchRequest;
use App\Services\Administrations\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para el mantenimiento de Sucursales empresariales.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService
    ) {}

    #[OA\Get(
        path: '/api/v1/administration/branches',
        summary: 'Listar todas las sucursales',
        operationId: 'listBranches',
        tags: ['Sucursal'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación exitosa.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');
            $data = $this->branchService->getAllBranchesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Sucursales obtenidas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/branches/list',
        summary: 'Obtener catálogo de Sucursal',
        operationId: 'listSucursal',
        tags: ['Sucursal'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->query('company_uuid') ?? $request->input('filter.company_uuid');
            $data = $this->branchService->getAllBranches($companyUuid);

            return $this->successResponse($data, 'Catálogo completo de sucursal obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/branches',
        summary: 'Crear nueva sucursal',
        operationId: 'storeBranch',
        tags: ['Sucursal'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Sucursal creada.'),
        ]
    )]
    public function store(StoreBranchRequest $request): JsonResponse
    {
        try {
            $record = $this->branchService->createBranch($request->validated());

            return $this->successResponse($record, 'Sucursal creada correctamente.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/branches/{uuid}',
        summary: 'Ver información de una sucursal específica',
        operationId: 'showBranch',
        tags: ['Sucursal'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Información de sucursal recuperada.'),
            new OA\Response(response: 404, description: 'Sucursal no encontrada.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->branchService->getBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La sucursal solicitada no existe.', 404);
            }

            return $this->successResponse($record, 'Detalles de la sucursal obtenidos.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/branches/{uuid}',
        summary: 'Actualizar datos de una sucursal',
        operationId: 'updateBranch',
        tags: ['Sucursal'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucursal actualizada.'),
        ]
    )]
    public function update(UpdateBranchRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->branchService->getBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar una sucursal inexistente.', 404);
            }
            $updated = $this->branchService->updateBranch($uuid, $request->validated());

            return $this->successResponse($updated, 'Sucursal actualizada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/branches/{uuid}',
        summary: 'Eliminar sucursal',
        operationId: 'destroyBranch',
        tags: ['Sucursal'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sucursal eliminada.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->branchService->getBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Sucursal no encontrada.', 404);
            }
            $this->branchService->deleteBranch($uuid);

            return $this->successResponse(null, 'Sucursal eliminada satisfactoriamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/branches/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo de Sucursal',
        operationId: 'toggleStatusSucursal',
        tags: ['Sucursal'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado con éxito.'),
            new OA\Response(response: 404, description: 'Registro no encontrado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function toggleStatus(string $uuid): JsonResponse
    {
        try {
            $record = $this->branchService->getBranchByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que intenta modificar no existe.', 404);
            }
            $updated = $this->branchService->toggleBranchStatus($uuid);
            $activo = $updated->is_active ?? $updated->status ?? false;
            $estado = $activo ? 'activado' : 'desactivado';

            return $this->successResponse($updated, ucfirst('sucursal').' '.$estado.' correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
