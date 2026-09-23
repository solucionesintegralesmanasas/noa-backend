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
    public const RANGO_MAXIMO_DIAS = 31;
    public const HISTORIAL_POR_PAGINA = 500;
    public const HISTORIAL_POR_PAGINA_MAX = 2000;
    public const MAPA_MAX_PUNTOS = 1000;
    public const MAPA_MAX_PUNTOS_TOPE = 5000;

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
     * Base del historial: conductor + empresa + rango, sin relaciones.
     * El trazado solo necesita latitud, longitud, velocidad y fecha.
     */
    private function rangoQuery(
        string $driverUuid,
        ?string $companyUuid,
        Carbon $start,
        Carbon $end
    ): Builder {
        $query = DriverLocation::where('third_party_uuid', $driverUuid)
            ->whereBetween('recorded_at', [$start, $end]);

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query;
    }

    private function rangoFechas(string $startDate, string $endDate): array
    {
        return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
    }

    /**
     * Historial paginado de ubicaciones (ARQ-002): nunca devuelve el rango completo.
     *
     * @return array{data: \Illuminate\Support\Collection, meta: array}
     */
    public function getDriverHistory(
        string $driverUuid,
        string $startDate,
        string $endDate,
        ?string $companyUuid = null,
        int $perPage = self::HISTORIAL_POR_PAGINA,
        int $page = 1
    ): array {
        [$start, $end] = $this->rangoFechas($startDate, $endDate);

        $perPage = max(1, min($perPage, self::HISTORIAL_POR_PAGINA_MAX));
        $page = max(1, $page);

        $query = $this->rangoQuery($driverUuid, $companyUuid, $start, $end);

        $total = (clone $query)->count();
        $items = (clone $query)
            ->orderBy('recorded_at', 'asc')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get(['latitude', 'longitude', 'speed', 'recorded_at']);

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Trazado decimado para el mapa (ARQ-002): como máximo $maxPoints puntos
     * muestreados por buckets de tiempo. La forma del recorrido se conserva
     * sin transferir ni hidratar el rango completo.
     */
    public function getDriverHistoryForMap(
        string $driverUuid,
        string $startDate,
        string $endDate,
        ?string $companyUuid = null,
        int $maxPoints = self::MAPA_MAX_PUNTOS
    ): \Illuminate\Support\Collection {
        [$start, $end] = $this->rangoFechas($startDate, $endDate);

        $maxPoints = max(100, min($maxPoints, self::MAPA_MAX_PUNTOS_TOPE));

        // Carbon 3 devuelve diffs con signo: se fuerza valor absoluto.
        $rangeSeconds = max(1, (int) abs($end->diffInSeconds($start)));
        $bucketSeconds = max(1, (int) ceil($rangeSeconds / $maxPoints));

        return $this->rangoQuery($driverUuid, $companyUuid, $start, $end)
            ->selectRaw('AVG(latitude) AS latitude, AVG(longitude) AS longitude, AVG(speed) AS speed, MIN(recorded_at) AS recorded_at')
            ->groupBy(DB::raw('FLOOR(UNIX_TIMESTAMP(recorded_at) / ' . $bucketSeconds . ')'))
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * Estadísticas de conducción (ARQ-002).
     *
     * Sin rango devuelve solo sesiones (barato). Con rango devuelve además los
     * agregados del periodo calculados en SQL, sin hidratar filas.
     * Los bloques today/week/month se eliminaron: ningún consumidor los leía.
     */
    public function getDriverStats(
        string $driverUuid,
        ?string $companyUuid = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $range = null;
        if ($startDate && $endDate) {
            [$start, $end] = $this->rangoFechas($startDate, $endDate);
            $range = $this->calculateStats($driverUuid, $companyUuid, $start, $end);
        }

        $totalSessions = DriverLocationSession::where('third_party_uuid', $driverUuid)
            ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
            ->count();

        $activeSession = DriverLocationSession::where('third_party_uuid', $driverUuid)
            ->where('status', 'active')
            ->first();

        return [
            'range' => $range,
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
     * Agregados de un rango sin hidratar filas (ARQ-002).
     *
     * La distancia usa ventana LAG() + haversine en SQL: mismo resultado que el
     * cálculo anterior en PHP, sin cargar los puntos en memoria.
     */
    private function calculateStats(
        string $driverUuid,
        ?string $companyUuid,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $where = 'third_party_uuid = ? AND recorded_at BETWEEN ? AND ?';
        $bindings = [$driverUuid, $startDate->toDateTimeString(), $endDate->toDateTimeString()];
        if ($companyUuid) {
            $where .= ' AND company_uuid = ?';
            $bindings[] = $companyUuid;
        }

        $agg = DB::selectOne(
            'SELECT COUNT(*) AS total_points, MAX(speed) AS max_speed, ' .
            'AVG(CASE WHEN speed > 0 THEN speed END) AS avg_speed, ' .
            'MIN(recorded_at) AS start_time, MAX(recorded_at) AS end_time ' .
            "FROM driver_locations WHERE {$where}",
            $bindings
        );

        if (! $agg || (int) $agg->total_points === 0) {
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

        $dist = DB::selectOne(
            'SELECT COALESCE(SUM(6371000 * 2 * ATAN2(SQRT(a), SQRT(GREATEST(1 - a, 0)))), 0) AS m FROM (' .
            'SELECT LEAST(POW(SIN(RADIANS(lat - prev_lat) / 2), 2) + COS(RADIANS(prev_lat)) * COS(RADIANS(lat)) * POW(SIN(RADIANS(lng - prev_lng) / 2), 2), 1) AS a FROM (' .
            'SELECT latitude AS lat, longitude AS lng, ' .
            'LAG(latitude) OVER (ORDER BY recorded_at, id) AS prev_lat, ' .
            'LAG(longitude) OVER (ORDER BY recorded_at, id) AS prev_lng ' .
            "FROM driver_locations WHERE {$where}) p WHERE prev_lat IS NOT NULL) d",
            $bindings
        );

        return [
            'total_distance_km' => round(((float) $dist->m) / 1000, 2),
            'avg_speed_kmh' => $agg->avg_speed !== null ? round((float) $agg->avg_speed, 1) : 0,
            'max_speed_kmh' => $agg->max_speed !== null ? round((float) $agg->max_speed, 1) : 0,
            'total_points' => (int) $agg->total_points,
            'duration_minutes' => Carbon::parse($agg->start_time)->diffInMinutes(Carbon::parse($agg->end_time)),
            'start_time' => Carbon::parse($agg->start_time)->toIso8601String(),
            'end_time' => Carbon::parse($agg->end_time)->toIso8601String(),
        ];
    }

    /**
     * Obtiene el recorrido GPS de un vehículo dentro de un proyecto para un día específico.
     * Filtro usado por el PDF diario de planilla: vehicle_uuid + project_uuid + fecha de servicio.
     *
     * @param  string|null  $vehicleUuid  UUID del vehículo (puede ser nulo para subcontratados sin uuid)
     * @param  string|null  $projectUuid  UUID del proyecto
     * @param  string|\Illuminate\Support\Carbon  $serviceDate  Fecha del servicio (Y-m-d)
     * @param  string|null  $companyUuid  UUID de la empresa (opcional)
     */
    public function getVehicleProjectDayHistory(
        ?string $vehicleUuid,
        ?string $projectUuid,
        mixed $serviceDate,
        ?string $companyUuid = null
    ): \Illuminate\Support\Collection {
        $day = Carbon::parse($serviceDate);

        $query = DriverLocation::query()
            ->whereBetween('recorded_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('recorded_at', 'asc');

        if (! empty($vehicleUuid)) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        if (! empty($projectUuid)) {
            $query->where('project_uuid', $projectUuid);
        }

        if (! empty($companyUuid)) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->get(['latitude', 'longitude', 'speed', 'recorded_at', 'vehicle_uuid', 'project_uuid']);
    }
}