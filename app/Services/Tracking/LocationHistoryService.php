<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\DriverLocation;
use App\Models\DriverLocationSession;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para historial de rutas y estadísticas de conductores.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class LocationHistoryService extends BaseService
{
    protected function getModelInstance(): Model
    {
        return new DriverLocation;
    }

    public function query(): Builder
    {
        return $this->model->newQuery()
            ->with(['driver', 'vehicle', 'project']);
    }

    /**
     * Obtiene el historial de ubicaciones de un conductor en un rango de fechas.
     *
     * @param  string  $driverUuid  UUID del conductor (third_party_uuid)
     * @param  string  $startDate   Fecha inicio (Y-m-d)
     * @param  string  $endDate     Fecha fin (Y-m-d)
     * @param  string|null  $companyUuid  UUID de la empresa
     */
    public function getDriverHistory(
        string $driverUuid,
        string $startDate,
        string $endDate,
        ?string $companyUuid = null
    ): \Illuminate\Support\Collection {
        $query = $this->query()
            ->where('third_party_uuid', $driverUuid)
            ->whereBetween('recorded_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('recorded_at', 'asc');

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->get();
    }

    /**
     * Obtiene las estadísticas de conducción de un conductor.
     */
    public function getDriverStats(
        string $driverUuid,
        ?string $companyUuid = null
    ): array {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();

        $baseQuery = DriverLocation::where('third_party_uuid', $driverUuid);

        if ($companyUuid) {
            $baseQuery->where('company_uuid', $companyUuid);
        }

        $todayStats = $this->calculateStats(clone $baseQuery, $today, $today->copy()->endOfDay());
        $weekStats = $this->calculateStats(clone $baseQuery, $weekStart, Carbon::now());
        $monthStats = $this->calculateStats(clone $baseQuery, $monthStart, Carbon::now());

        $totalSessions = DriverLocationSession::where('third_party_uuid', $driverUuid)
            ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
            ->count();

        $activeSession = DriverLocationSession::where('third_party_uuid', $driverUuid)
            ->where('status', 'active')
            ->first();

        return [
            'today' => $todayStats,
            'week' => $weekStats,
            'month' => $monthStats,
            'total_sessions' => $totalSessions,
            'active_session' => $activeSession ? [
                'uuid' => $activeSession->uuid,
                'started_at' => $activeSession->started_at,
                'total_distance_km' => $activeSession->total_distance_km,
                'total_points' => $activeSession->total_points,
            ] : null,
        ];
    }

    /**
     * Calcula estadísticas para un rango de fechas específico.
     */
    private function calculateStats(Builder $query, Carbon $startDate, Carbon $endDate): array
    {
        $locations = $query->clone()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'asc')
            ->get(['latitude', 'longitude', 'speed', 'recorded_at']);

        if ($locations->isEmpty()) {
            return [
                'total_distance_km' => 0,
                'avg_speed_kmh' => 0,
                'max_speed_kmh' => 0,
                'total_points' => 0,
                'duration_minutes' => 0,
                'start_time' => null,
                'end_time' => null,
            ];
        }

        $totalDistance = 0;
        $maxSpeed = 0;
        $speedSum = 0;
        $speedCount = 0;

        for ($i = 1; $i < $locations->count(); $i++) {
            $prev = $locations[$i - 1];
            $curr = $locations[$i];

            $totalDistance += $this->haversineDistance(
                (float) $prev->latitude,
                (float) $prev->longitude,
                (float) $curr->latitude,
                (float) $curr->longitude
            );

            $speed = (float) $curr->speed;
            if ($speed > 0) {
                $speedSum += $speed;
                $speedCount++;
            }
            if ($speed > $maxSpeed) {
                $maxSpeed = $speed;
            }
        }

        $startTime = $locations->first()->recorded_at;
        $endTime = $locations->last()->recorded_at;
        $durationMinutes = $startTime->diffInMinutes($endTime);

        return [
            'total_distance_km' => round($totalDistance / 1000, 2),
            'avg_speed_kmh' => $speedCount > 0 ? round($speedSum / $speedCount, 1) : 0,
            'max_speed_kmh' => round($maxSpeed, 1),
            'total_points' => $locations->count(),
            'duration_minutes' => $durationMinutes,
            'start_time' => $startTime->toIso8601String(),
            'end_time' => $endTime->toIso8601String(),
        ];
    }

    /**
     * Calcula distancia Haversine en metros.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}