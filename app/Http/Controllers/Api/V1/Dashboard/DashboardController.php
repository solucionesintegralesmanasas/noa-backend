<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created_at 2026-06-30
 *
 * @module Dashboard
 *
 * @resource Dashboard
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $data = $this->dashboardService->getSummary($days);

        return $this->successResponse($data, 'Métricas obtenidas exitosamente.');
    }

    public function recentActivity(): JsonResponse
    {
        $data = $this->dashboardService->getRecentActivity();

        return $this->successResponse($data, 'Actividad reciente obtenida exitosamente.');
    }

    public function alerts(): JsonResponse
    {
        $data = $this->dashboardService->getAlerts();

        return $this->successResponse($data, 'Alertas obtenidas exitosamente.');
    }
}
