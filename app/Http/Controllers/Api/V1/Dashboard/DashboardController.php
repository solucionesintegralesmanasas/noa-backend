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

    public function conductorSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorizedResponse('Usuario no autenticado.');
        }

        $hasConductorRole = method_exists($user, 'hasRole') && $user->hasRole('CONDUCTOR');
        $isAdminOrSuper = method_exists($user, 'hasRole') && (
            $user->hasRole('super-admin') ||
            $user->hasRole('Super Admin') ||
            $user->hasRole('ADMINISTRADOR') ||
            $user->hasRole('admin')
        );

        if (! $hasConductorRole && ! $isAdminOrSuper) {
            return $this->forbiddenResponse('Acceso denegado: este recurso es exclusivo para el rol CONDUCTOR.');
        }

        $days = (int) $request->query('days', 30);
        $data = $this->dashboardService->getConductorSummary($days);

        return $this->successResponse($data, 'Métricas del conductor obtenidas exitosamente.');
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
