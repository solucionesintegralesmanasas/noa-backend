<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\DriverLocation;
use App\Models\DriverLocationAlert;
use App\Models\DriverLocationSession;
use App\Models\Geofence;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Servicio principal de tracking GPS para conductores.
 *
 * Recibe ubicaciones del dispositivo, las persiste, actualiza sesiones
 * activas y procesa alertas de geocercas en cada punto recibido.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class LocationTrackingService extends BaseService
{
    protected array $searchableFields = ['latitude', 'longitude', 'speed'];

    public function __construct(
        private readonly GeofenceService $geofenceService
    ) {
        parent::__construct();
    }

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
     * Registra una nueva ubicación GPS del conductor.
     * Procesa geocercas y genera alertas cuando corresponde.
     */
    public function storeLocation(array $data): DriverLocation
    {
        return $this->transaction(function () use ($data) {
            $location = DriverLocation::create([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $data['company_uuid'],
                'third_party_uuid' => $data['third_party_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'project_uuid' => $data['project_uuid'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'altitude' => $data['altitude'] ?? null,
                'speed' => $data['speed'] ?? 0,
                'heading' => $data['heading'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'battery_level' => $data['battery_level'] ?? null,
                'is_moving' => $data['is_moving'] ?? false,
                'source' => $data['source'] ?? 'gps',
                'recorded_at' => $data['recorded_at'] ?? now(),
            ]);

            $this->updateActiveSession($location);
            $this->processGeofences($location);

            return $location;
        });
    }

    /**
     * Inicia una nueva sesión de tracking para un conductor.
     */
    public function startSession(array $data): DriverLocationSession
    {
        return $this->transaction(function () use ($data) {
            DriverLocationSession::where('third_party_uuid', $data['third_party_uuid'])
                ->where('status', 'active')
                ->update(['status' => 'paused', 'ended_at' => now()]);

            return DriverLocationSession::create([
                'uuid' => (string) Str::uuid(),
                'company_uuid' => $data['company_uuid'],
                'third_party_uuid' => $data['third_party_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'project_uuid' => $data['project_uuid'] ?? null,
                'started_at' => now(),
                'status' => 'active',
            ]);
        });
    }

    /**
     * Detiene la sesión de tracking activa de un conductor.
     */
    public function stopSession(string $sessionUuid): bool
    {
        return $this->transaction(function () use ($sessionUuid) {
            $session = DriverLocationSession::where('uuid', $sessionUuid)
                ->where('status', 'active')
                ->first();

            if (! $session) {
                return false;
            }

            $session->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Obtiene la lista de conductores con sesión GPS activa en tiempo real.
     * Filtra por sesiones con status='active' y retorna la última ubicación de cada uno.
     */
    public function getActiveDrivers(?string $companyUuid): \Illuminate\Support\Collection
    {
        $activeDriverUuids = DriverLocationSession::query()
            ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
            ->where('status', 'active')
            ->pluck('third_party_uuid');

        if ($activeDriverUuids->isEmpty()) {
            return collect();
        }

        $latestPerDriver = DriverLocation::select(
            DB::raw('MAX(id) as max_id')
        )
            ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
            ->whereIn('third_party_uuid', $activeDriverUuids)
            ->groupBy('third_party_uuid');

        return DriverLocation::whereIn('id', $latestPerDriver)
            ->with([
                'driver:id,uuid,first_name,last_name,document_number',
                'vehicle:id,uuid,vehicle_license_plate,model',
            ])
            ->get();
    }

    /**
     * Obtiene la última ubicación conocida de un conductor específico.
     */
    public function getLastLocation(string $driverUuid): ?DriverLocation
    {
        return DriverLocation::where('third_party_uuid', $driverUuid)
            ->latest('recorded_at')
            ->with(['driver', 'vehicle', 'project'])
            ->first();
    }

    /**
     * Obtiene alertas pendientes para una empresa.
     */
    public function getAlerts(string $companyUuid, int $perPage = 15, bool $onlyUnread = false): mixed
    {
        $query = DriverLocationAlert::where('company_uuid', $companyUuid)
            ->with(['driver:id,uuid,first_name,last_name']);

        if ($onlyUnread) {
            $query->where('is_read', false);
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    /**
     * Marca una alerta como leída.
     */
    public function markAlertRead(string $alertUuid): bool
    {
        $alert = DriverLocationAlert::where('uuid', $alertUuid)->first();

        if (! $alert) {
            return false;
        }

        return $alert->update(['is_read' => true]);
    }

    /**
     * Actualiza la sesión activa con la nueva ubicación recibida.
     */
    private function updateActiveSession(DriverLocation $location): void
    {
        $session = DriverLocationSession::where('third_party_uuid', $location->third_party_uuid)
            ->where('status', 'active')
            ->first();

        if ($session) {
            $lastPoint = DriverLocation::where('third_party_uuid', $location->third_party_uuid)
                ->where('id', '!=', $location->id)
                ->orderByDesc('recorded_at')
                ->first();

            $distanceKm = 0;
            if ($lastPoint) {
                $distanceKm = $this->haversineDistance(
                    (float) $lastPoint->latitude,
                    (float) $lastPoint->longitude,
                    (float) $location->latitude,
                    (float) $location->longitude
                ) / 1000;
            }

            $session->increment('total_distance_km', round($distanceKm, 4));
            $session->increment('total_points');
        }
    }

    /**
     * Procesa todas las geocercas activas para la empresa y genera alertas.
     */
    private function processGeofences(DriverLocation $location): void
    {
        $geofences = Geofence::where('company_uuid', $location->company_uuid)
            ->where('is_active', true)
            ->get();

        if ($geofences->isEmpty()) {
            return;
        }

        foreach ($geofences as $geofence) {
            $isInside = $this->isPointInsideGeofence($location, $geofence);

            if ($geofence->alert_on_exit) {
                $wasInside = $this->getPreviousGeofenceState($location->third_party_uuid, $geofence);

                if ($wasInside && ! $isInside) {
                    $this->createAlert([
                        'company_uuid' => $location->company_uuid,
                        'third_party_uuid' => $location->third_party_uuid,
                        'driver_location_uuid' => $location->uuid,
                        'alert_type' => 'geofence_exit',
                        'geofence_uuid' => $geofence->uuid,
                        'message' => "El conductor salió de la zona '{$geofence->name}'",
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                    ]);
                }
            }

            if ($geofence->alert_on_enter) {
                $wasInside = $this->getPreviousGeofenceState($location->third_party_uuid, $geofence);

                if (! $wasInside && $isInside) {
                    $this->createAlert([
                        'company_uuid' => $location->company_uuid,
                        'third_party_uuid' => $location->third_party_uuid,
                        'driver_location_uuid' => $location->uuid,
                        'alert_type' => 'geofence_enter',
                        'geofence_uuid' => $geofence->uuid,
                        'message' => "El conductor ingresó a la zona '{$geofence->name}'",
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                    ]);
                }
            }

            if ($geofence->max_speed_kmh && (float) $location->speed > $geofence->max_speed_kmh) {
                $this->createAlert([
                    'company_uuid' => $location->company_uuid,
                    'third_party_uuid' => $location->third_party_uuid,
                    'driver_location_uuid' => $location->uuid,
                    'alert_type' => 'overspeed',
                    'geofence_uuid' => $geofence->uuid,
                    'message' => "Exceso de velocidad: {$location->speed} km/h (máx: {$geofence->max_speed_kmh} km/h) en '{$geofence->name}'",
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                ]);
            }
        }
    }

    /**
     * Determina si un punto está dentro de la geocerca (círculo o polígono).
     */
    public function isPointInsideGeofence(DriverLocation $location, Geofence $geofence): bool
    {
        if ($geofence->type === 'circle') {
            $distance = $this->haversineDistance(
                (float) $location->latitude,
                (float) $location->longitude,
                (float) $geofence->center_lat,
                (float) $geofence->center_lng
            );

            return $distance <= $geofence->radius_meters;
        }

        return $this->pointInPolygon(
            (float) $location->latitude,
            (float) $location->longitude,
            $geofence->polygon_points ?? []
        );
    }

    /**
     * Verifica si el conductor estaba dentro de la geocerca en la ubicación anterior.
     */
    private function getPreviousGeofenceState(string $driverUuid, Geofence $geofence): bool
    {
        $previousLocation = DriverLocation::where('third_party_uuid', $driverUuid)
            ->orderByDesc('recorded_at')
            ->skip(1)
            ->first();

        if (! $previousLocation) {
            return false;
        }

        return $this->isPointInsideGeofence($previousLocation, $geofence);
    }

    /**
     * Calcula la distancia entre dos puntos usando la fórmula de Haversine.
     *
     * @return float Distancia en metros
     */
    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
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

    /**
     * Algoritmo Ray Casting para determinar si un punto está dentro de un polígono.
     *
     * Los vértices pueden venir como arreglos asociativos ['lat' =>, 'lng' =>]
     * o como arreglo indexado [lat, lng]. Internamente se trabaja con
     * coordenadas cartesianas (x = longitud, y = latitud).
     */
    private function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $n = count($polygon);

        if ($n < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = $polygon[$i]['lat'] ?? $polygon[$i][0];
            $xi = $polygon[$i]['lng'] ?? $polygon[$i][1];
            $yj = $polygon[$j]['lat'] ?? $polygon[$j][0];
            $xj = $polygon[$j]['lng'] ?? $polygon[$j][1];

            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Registra una alerta de ubicación.
     */
    private function createAlert(array $data): DriverLocationAlert
    {
        return DriverLocationAlert::create(array_merge([
            'uuid' => (string) Str::uuid(),
        ], $data));
    }
}