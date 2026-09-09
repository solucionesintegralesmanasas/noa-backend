<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\EnablingResolution\StoreEnablingResolutionRequest;
use App\Http\Requests\Administrations\EnablingResolution\UpdateEnablingResolutionRequest;
use App\Services\Administrations\EnablingResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Resoluciones de Habilitación.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class EnablingResolutionController extends Controller
{
    public function __construct(private readonly EnablingResolutionService $enablingResolutionService) {}

    #[OA\Get(
        path: '/api/v1/administration/enabling-resolutions',
        summary: 'Listar resoluciones de habilitación',
        operationId: 'listEnablingResolutions',
        tags: ['ResoluciónHabilitación'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado obtenido.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');

            $data = $this->enablingResolutionService->getAllEnablingResolutionsWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Resoluciones recuperadas exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/enabling-resolutions/list',
        summary: 'Obtener catálogo de ResoluciónHabilitación',
        operationId: 'getAllEnablingResolutions',
        tags: ['ResoluciónHabilitación'],
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
            $companyUuid = $request->query('company_uuid');
            $data = $this->enablingResolutionService->getAllEnablingResolutions($companyUuid);

            return $this->successResponse($data, 'Catálogo completo de resolución de habilitación obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/enabling-resolutions',
        summary: 'Crear nueva resolución',
        operationId: 'storeEnablingResolution',
        tags: ['ResoluciónHabilitación'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Creado correctamente.')]
    )]
    public function store(StoreEnablingResolutionRequest $request): JsonResponse
    {
        try {
            $record = $this->enablingResolutionService->createEnablingResolution($request->validated());

            return $this->successResponse($record, 'Resolución registrada con éxito.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/enabling-resolutions/{uuid}',
        summary: 'Ver detalle de resolución',
        operationId: 'showEnablingResolution',
        tags: ['ResoluciónHabilitación'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Resolución encontrada.'),
            new OA\Response(response: 404, description: 'No encontrada.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->enablingResolutionService->getEnablingResolutionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La resolución no existe.', 404);
            }

            return $this->successResponse($record, 'Información de la resolución obtenida.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/enabling-resolutions/{uuid}',
        summary: 'Actualizar datos de resolución',
        operationId: 'updateEnablingResolution',
        tags: ['ResoluciónHabilitación'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualización exitosa.')]
    )]
    public function update(UpdateEnablingResolutionRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->enablingResolutionService->getEnablingResolutionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Resolución inexistente.', 404);
            }
            $updated = $this->enablingResolutionService->updateEnablingResolution($uuid, $request->validated());

            return $this->successResponse($updated, 'Datos de resolución actualizados correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/enabling-resolutions/{uuid}',
        summary: 'Eliminar resolución',
        operationId: 'destroyEnablingResolution',
        tags: ['ResoluciónHabilitación'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Resolución eliminada.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->enablingResolutionService->getEnablingResolutionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La resolución ya no existe.', 404);
            }
            $this->enablingResolutionService->deleteEnablingResolution($uuid);

            return $this->successResponse(null, 'Resolución eliminada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/administration/enabling-resolutions/{uuid}/toggle-status',
        summary: 'Alternar estado activo/inactivo de ResoluciónHabilitación',
        operationId: 'toggleStatusEnablingResolution',
        tags: ['ResoluciónHabilitación'],
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
            $record = $this->enablingResolutionService->getEnablingResolutionByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro que intenta modificar no existe.', 404);
            }
            $updated = $this->enablingResolutionService->toggleEnablingResolutionStatus($uuid);
            $activo = $updated->is_active ?? $updated->status ?? false;
            $estado = $activo ? 'activado' : 'desactivado';

            return $this->successResponse($updated, ucfirst('resolución de habilitación').' '.$estado.' correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
