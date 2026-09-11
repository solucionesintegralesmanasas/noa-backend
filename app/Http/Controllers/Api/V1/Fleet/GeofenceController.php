<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\StoreGeofenceRequest;
use App\Services\Tracking\GeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API CRUD para la gestión de geocercas.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class GeofenceController extends Controller
{
    public function __construct(
        private readonly GeofenceService $geofenceService
    ) {}

    #[OA\Get(
        path: '/api/v1/tracking/geofences',
        summary: 'Listar geocercas de la empresa',
        operationId: 'listGeofences',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de geocercas.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', '15');
            $page = (int) $request->query('page', '1');
            $search = (string) $request->query('search', '');
            $companyUuid = $request->user()->companies()->first()->uuid ?? null;

            $data = $this->geofenceService->getGeofencesWithPagination($perPage, $page, $search, $companyUuid);

            return $this->paginatedResponse($data, 'Geocercas recuperadas con éxito.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/tracking/geofences',
        summary: 'Crear una nueva geocerca',
        operationId: 'storeGeofence',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Geocerca creada exitosamente.'),
        ]
    )]
    public function store(StoreGeofenceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $validated['geofence'];
            $data['company_uuid'] = $request->user()->companies()->first()->uuid ?? null;

            $geofence = $this->geofenceService->createGeofence($data);

            return $this->createdResponse($geofence, 'Geocerca creada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/tracking/geofences/{uuid}',
        summary: 'Obtener detalle de una geocerca',
        operationId: 'showGeofence',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Geocerca encontrada.'),
            new OA\Response(response: 404, description: 'Geocerca no encontrada.'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        try {
            $geofence = $this->geofenceService->findByUuid($uuid);

            if (! $geofence) {
                return $this->notFoundResponse('Geocerca no encontrada.');
            }

            return $this->successResponse($geofence, 'Geocerca recuperada.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/tracking/geofences/{uuid}',
        summary: 'Actualizar una geocerca',
        operationId: 'updateGeofence',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Geocerca actualizada.'),
        ]
    )]
    public function update(StoreGeofenceRequest $request, string $uuid): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $validated['geofence'];

            $result = $this->geofenceService->updateGeofence($uuid, $data);

            if (! $result) {
                return $this->notFoundResponse('Geocerca no encontrada.');
            }

            $geofence = $this->geofenceService->findByUuid($uuid);

            return $this->successResponse($geofence, 'Geocerca actualizada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/tracking/geofences/{uuid}',
        summary: 'Eliminar una geocerca',
        operationId: 'deleteGeofence',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Geocerca eliminada.'),
        ]
    )]
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->geofenceService->deleteGeofence($uuid);

            if (! $result) {
                return $this->notFoundResponse('Geocerca no encontrada.');
            }

            return $this->successResponse(null, 'Geocerca eliminada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}