<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrations\EconomicActivity\StoreEconomicActivityRequest;
use App\Http\Requests\Administrations\EconomicActivity\UpdateEconomicActivityRequest;
use App\Services\Administrations\EconomicActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de Actividades Económicas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class EconomicActivityController extends Controller
{
    public function __construct(
        private readonly EconomicActivityService $economicActivityService
    ) {}

    #[OA\Get(
        path: '/api/v1/administration/economic-activities',
        summary: 'Consultar listado paginado de Actividades Económicas',
        operationId: 'listEconomicActivities',
        tags: ['ActividadEconómica'],
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
            $companyUuid = $request->query('company_uuid');
            $data = $this->economicActivityService->getAllEconomicActivitiesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->successResponse($data, 'Listado de actividades recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/economic-activities/list',
        summary: 'Obtener catálogo de ActividadEconómica',
        operationId: 'getAllEconomicActivities',
        tags: ['ActividadEconómica'],
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
            $data = $this->economicActivityService->getAllEconomicActivities();

            return $this->successResponse($data, 'Catálogo completo de actividad económica obtenido.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/administration/economic-activities',
        summary: 'Crear nueva actividad económica',
        operationId: 'storeEconomicActivity',
        tags: ['ActividadEconómica'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Actividad creada con éxito.'),
        ]
    )]
    public function store(StoreEconomicActivityRequest $request): JsonResponse
    {
        try {
            $record = $this->economicActivityService->createEconomicActivity($request->validated());

            return $this->successResponse($record, 'Actividad económica registrada.', 201);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/administration/economic-activities/{uuid}',
        summary: 'Ver detalle de actividad económica',
        operationId: 'showEconomicActivity',
        tags: ['ActividadEconómica'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle recuperado.'),
            new OA\Response(response: 404, description: 'No encontrado.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $record = $this->economicActivityService->getEconomicActivityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Actividad no encontrada.', 404);
            }

            return $this->successResponse($record, 'Información de actividad económica obtenida.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/administration/economic-activities/{uuid}',
        summary: 'Actualizar actividad económica',
        operationId: 'updateEconomicActivity',
        tags: ['ActividadEconómica'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actualización exitosa.'),
        ]
    )]
    public function update(UpdateEconomicActivityRequest $request, string $uuid): JsonResponse
    {
        try {
            $record = $this->economicActivityService->getEconomicActivityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('Actividad inexistente.', 404);
            }
            $updated = $this->economicActivityService->updateEconomicActivity($uuid, $request->validated());

            return $this->successResponse($updated, 'Actividad económica actualizada correctamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/administration/economic-activities/{uuid}',
        summary: 'Eliminar actividad económica',
        operationId: 'destroyEconomicActivity',
        tags: ['ActividadEconómica'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Actividad eliminada.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $record = $this->economicActivityService->getEconomicActivityByUuid($uuid);
            if (! $record) {
                return $this->errorResponse('La actividad no existe.', 404);
            }
            $this->economicActivityService->deleteEconomicActivity($uuid);

            return $this->successResponse(null, 'Actividad económica removida.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
