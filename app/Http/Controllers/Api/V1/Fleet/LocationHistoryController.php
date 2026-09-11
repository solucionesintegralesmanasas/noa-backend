<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Services\Tracking\LocationHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controlador API para historial de rutas y estadísticas de conductores.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class LocationHistoryController extends Controller
{
    public function __construct(
        private readonly LocationHistoryService $historyService
    ) {}

    #[OA\Get(
        path: '/api/v1/tracking/driver/{uuid}/history',
        summary: 'Obtener historial de ruta de un conductor',
        operationId: 'driverHistory',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historial de ubicaciones.'),
        ]
    )]
    public function driverHistory(Request $request, string $uuid): JsonResponse
    {
        try {
            $startDate = $request->query('start_date', now()->subDays(7)->format('Y-m-d'));
            $endDate = $request->query('end_date', now()->format('Y-m-d'));
            $companyUuid = $request->user()->companies()->first()->uuid ?? null;

            $history = $this->historyService->getDriverHistory($uuid, $startDate, $endDate, $companyUuid);

            return $this->successResponse($history, 'Historial de ruta recuperado.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/tracking/driver/{uuid}/stats',
        summary: 'Obtener estadísticas de conducción',
        operationId: 'driverStats',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas del conductor.'),
        ]
    )]
    public function driverStats(Request $request, string $uuid): JsonResponse
    {
        try {
            $companyUuid = $request->user()->companies()->first()->uuid ?? null;

            $stats = $this->historyService->getDriverStats($uuid, $companyUuid);

            return $this->successResponse($stats, 'Estadísticas del conductor recuperadas.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}