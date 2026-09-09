<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\Experience\StoreExperienceRequest;
use App\Http\Requests\Administrations\Experience\UpdateExperienceRequest;
use App\Services\Administrations\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Experiencias Comerciales.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ExperienceController extends Controller
{
    public function __construct(private readonly ExperienceService $experienceService) {}

    #[OA\Get(
        path: '/api/v1/administration/experiences',
        summary: 'Consultar listado de experiencias comerciales',
        operationId: 'listExperiences',
        tags: ['Experiencia'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Listado recuperado exitosamente.')]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->query('company_uuid');

            $data = $this->experienceService->getAllExperiencesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Historial de experiencias obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/experiences/list',
        summary: 'Obtener catálogo de Experiencia',
        operationId: 'listExperiencia',
        tags: ['Experiencia'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Operación realizada con éxito.'),
            new OA\Response(response: 401, description: 'No autorizado.'),
            new OA\Response(response: 500, description: 'Error interno del servidor.'),
        ]
    )]
    public function list(): JsonResponse
    {
        try {
            $data = $this->experienceService->getAllExperiences();

            return $this->successResponse($data, 'Catálogo completo de experiencia obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/experiences',
        summary: 'Registrar nueva experiencia contractual',
        operationId: 'storeExperience',
        tags: ['Experiencia'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Registro creado.')]
    )]
    public function store(StoreExperienceRequest $request): JsonResponse
    {
        try {
            $record = $this->experienceService->createExperience($request->validated());

            return $this->successResponse($record, 'Nueva experiencia comercial vinculada.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/experiences/{uuid}',
        summary: 'Consultar detalle de una experiencia',
        operationId: 'showExperience',
        tags: ['Experiencia'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Experiencia encontrada.'),
            new OA\Response(response: 404, description: 'No se encontró la experiencia.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->experienceService->getExperienceByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('El registro de experiencia solicitado no existe.', 404);
            }

            return $this->successResponse($record, 'Detalles de la experiencia contractual obtenidos.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/experiences/{uuid}',
        summary: 'Modificar datos de experiencia comercial',
        operationId: 'updateExperience',
        tags: ['Experiencia'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Actualizado correctamente.')]
    )]
    public function update(UpdateExperienceRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->experienceService->getExperienceByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('No se puede actualizar una experiencia inexistente.', 404);
            }
            $updated = $this->experienceService->updateExperience($uuid, $request->validated());

            return $this->successResponse($updated, 'Información de la experiencia actualizada.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/experiences/{uuid}',
        summary: 'Eliminar registro de experiencia',
        operationId: 'destroyExperience',
        tags: ['Experiencia'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Registro eliminado exitosamente.')]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->experienceService->getExperienceByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La experiencia ya no se encuentra en el sistema.', 404);
            }
            $this->experienceService->deleteExperience($uuid);

            return $this->successResponse(null, 'Experiencia comercial eliminada del registro.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
