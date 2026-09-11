<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\StoreLocationRequest;
use App\Services\Tracking\LocationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para la gestión de ubicaciones GPS de conductores.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class DriverLocationController extends Controller
{
    public function __construct(
        private readonly LocationTrackingService $trackingService
    ) {}

    #[OA\Post(
        path: '/api/v1/tracking/location',
        summary: 'Registrar ubicación GPS del conductor',
        operationId: 'storeLocation',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Ubicación registrada exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
        ]
    )]
    public function store(StoreLocationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $data = $validated['location'];
            $data['company_uuid'] = $request->user()->companies()->first()->uuid ?? null;
            $data['third_party_uuid'] = $request->user()->companies()->first()->pivot->third_party_uuid ?? null;

            $location = $this->trackingService->storeLocation($data);

            return $this->createdResponse($location, 'Ubicación registrada exitosamente.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/tracking/session/start',
        summary: 'Iniciar sesión de tracking',
        operationId: 'startSession',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Sesión iniciada.'),
        ]
    )]
    public function startSession(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $company = $user->companies()->first();
            $thirdPartyUuid = $company?->pivot->third_party_uuid;

            $session = $this->trackingService->startSession([
                'company_uuid' => $company?->uuid,
                'third_party_uuid' => $thirdPartyUuid,
                'vehicle_uuid' => $request->input('vehicle_uuid'),
                'project_uuid' => $request->input('project_uuid'),
            ]);

            return $this->createdResponse($session, 'Sesión de tracking iniciada.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/tracking/session/stop',
        summary: 'Detener sesión de tracking',
        operationId: 'stopSession',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión detenida.'),
        ]
    )]
    public function stopSession(Request $request): JsonResponse
    {
        try {
            $sessionUuid = $request->input('session_uuid');

            $result = $this->trackingService->stopSession($sessionUuid);

            if (! $result) {
                return $this->notFoundResponse('Sesión activa no encontrada.');
            }

            return $this->successResponse(null, 'Sesión de tracking detenida.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/tracking/active-drivers',
        summary: 'Obtener conductores con ubicación en tiempo real',
        operationId: 'activeDrivers',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de conductores activos.'),
        ]
    )]
    public function activeDrivers(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->user()->companies()->first()?->uuid;

            $drivers = $this->trackingService->getActiveDrivers($companyUuid);

            return $this->successResponse($drivers, 'Conductores activos recuperados.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/tracking/last-location/{uuid}',
        summary: 'Obtener última ubicación de un conductor',
        operationId: 'lastLocation',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Última ubicación del conductor.'),
        ]
    )]
    public function lastLocation(string $uuid): JsonResponse
    {
        try {
            $location = $this->trackingService->getLastLocation($uuid);

            if (! $location) {
                return $this->notFoundResponse('No se encontró ubicación para este conductor.');
            }

            return $this->successResponse($location, 'Última ubicación recuperada.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/tracking/alerts',
        summary: 'Obtener alertas de ubicación',
        operationId: 'getAlerts',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de alertas.'),
        ]
    )]
    public function alerts(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->user()->companies()->first()->uuid ?? null;
            $perPage = (int) $request->query('per_page', '15');
            $onlyUnread = $request->boolean('only_unread', false);

            $alerts = $this->trackingService->getAlerts($companyUuid, $perPage, $onlyUnread);

            return $this->paginatedResponse($alerts, 'Alertas recuperadas.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Patch(
        path: '/api/v1/tracking/alerts/{uuid}/read',
        summary: 'Marcar alerta como leída',
        operationId: 'markAlertRead',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Alerta marcada como leída.'),
        ]
    )]
    public function markAlertRead(string $uuid): JsonResponse
    {
        try {
            $result = $this->trackingService->markAlertRead($uuid);

            if (! $result) {
                return $this->notFoundResponse('Alerta no encontrada.');
            }

            return $this->successResponse(null, 'Alerta marcada como leída.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}