<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Services\Tracking\LocationHistoryService;
use Carbon\Carbon;
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
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 500, maximum: 2000)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'mode', in: 'query', required: false, description: 'points: página del historial; map: trazado decimado para el mapa', schema: new OA\Schema(type: 'string', enum: ['points', 'map'], default: 'points')),
            new OA\Parameter(name: 'max_points', in: 'query', required: false, description: 'Solo en mode=map: máximo de puntos del trazado', schema: new OA\Schema(type: 'integer', default: 1000, maximum: 5000)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historial de ubicaciones.'),
            new OA\Response(response: 422, description: 'Rango inválido o mayor de 31 días.'),
        ]
    )]
    public function driverHistory(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'mode' => ['sometimes', 'in:points,map'],
            'max_points' => ['sometimes', 'integer', 'min:100', 'max:5000'],
        ]);

        $this->validarRango($validated['start_date'], $validated['end_date']);
        $companyUuid = $request->user()->companies()->first()->uuid ?? null;

        try {
            if (($validated['mode'] ?? 'points') === 'map') {
                $items = $this->historyService->getDriverHistoryForMap(
                    $uuid,
                    $validated['start_date'],
                    $validated['end_date'],
                    $companyUuid,
                    (int) ($validated['max_points'] ?? 1000)
                );

                return $this->successResponse(
                    $items,
                    'Trazado del recorrido recuperado.',
                    200,
                    ['mode' => 'map', 'points' => $items->count()]
                );
            }

            $result = $this->historyService->getDriverHistory(
                $uuid,
                $validated['start_date'],
                $validated['end_date'],
                $companyUuid,
                (int) ($validated['per_page'] ?? 500),
                (int) ($validated['page'] ?? 1)
            );

            return $this->successResponse($result['data'], 'Historial de ruta recuperado.', 200, $result['meta']);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * El rango máximo es LocationHistoryService::RANGO_MAXIMO_DIAS (ARQ-002):
     * acota el peor caso de escaneo.
     */
    private function validarRango(string $startDate, string $endDate): void
    {
        // Carbon 3 devuelve diffs con signo: se fuerza valor absoluto.
        $dias = abs(Carbon::parse($startDate)->startOfDay()
            ->diffInDays(Carbon::parse($endDate)->endOfDay()));

        if ($dias > LocationHistoryService::RANGO_MAXIMO_DIAS) {
            abort(422, 'El rango máximo permitido es de ' . LocationHistoryService::RANGO_MAXIMO_DIAS . ' días.');
        }
    }

    #[OA\Get(
        path: '/api/v1/tracking/driver/{uuid}/stats',
        summary: 'Obtener estadísticas de conducción',
        operationId: 'driverStats',
        tags: ['Geolocalización'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Si se envía junto a end_date, devuelve los agregados del rango en "range". Sin rango solo devuelve sesiones.', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas del conductor.'),
            new OA\Response(response: 422, description: 'Rango inválido o mayor de 31 días.'),
        ]
    )]
    public function driverStats(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
        ]);

        $conInicio = isset($validated['start_date']);
        $conFin = isset($validated['end_date']);
        if ($conInicio !== $conFin) {
            abort(422, 'Envía start_date y end_date juntos para filtrar por rango.');
        }
        if ($conInicio && $conFin) {
            if (Carbon::parse($validated['end_date'])->lt(Carbon::parse($validated['start_date']))) {
                abort(422, 'end_date debe ser posterior o igual a start_date.');
            }
            $this->validarRango($validated['start_date'], $validated['end_date']);
        }

        try {
            $companyUuid = $request->user()->companies()->first()->uuid ?? null;

            $stats = $this->historyService->getDriverStats(
                $uuid,
                $companyUuid,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return $this->successResponse($stats, 'Estadísticas del conductor recuperadas.');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}