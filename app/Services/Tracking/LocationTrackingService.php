<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\DriverLocation;
use App\Models\DriverLocationAlert;
use App\Models\DriverLocationDailyStat;
use App\Models\DriverLocationSession;
use App\Models\Geofence;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
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
        if (empty($data['company_uuid']) || empty($data['third_party_uuid'])) {
            throw new \InvalidArgumentException('Faltan campos requeridos para la operación: company_uuid y third_party_uuid son obligatorios.');
        }

        return $this->transaction(function () use ($data) {
            $recordedAt = $data['recorded_at'] ?? now();

            // La distancia se calcula antes de insertar: el punto anterior del
            // conductor ya existe y así el nuevo se guarda una sola vez.
            $anterior = $this->puntoAnterior($data['third_party_uuid'], $data['company_uuid'], $recordedAt);

            $location = new DriverLocation([
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
                'recorded_at' => $recordedAt,
            ]);

            // La distancia la calcula el servidor: no es asignable en masa.
            $location->distance_meters = $anterior
                ? $this->distanciaEntrePuntos($anterior, $location)
                : 0.0;

            $location->save();

            $this->actualizarResumenDiario($location);
            $this->recalcularPuntoSiguiente($location);
            $this->updateActiveSession($location);
            $this->processGeofences($location, $anterior);

            return $location;
        });
    }

    /**
     * Inicia una nueva sesión de tracking para un conductor.
     */
    public function startSession(array $data): DriverLocationSession
    {
        if (empty($data['company_uuid']) || empty($data['third_party_uuid'])) {
            throw new \InvalidArgumentException('Faltan campos requeridos para la operación: company_uuid y third_party_uuid son obligatorios.');
        }

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

        $ubicaciones = DriverLocation::whereIn('id', $latestPerDriver)
            ->with([
                'driver:id,uuid,first_name,last_name,document_number',
                'vehicle:id,uuid,vehicle_license_plate,model',
                'project:uuid,project_name',
            ])
            ->get();

        $this->adjuntarPlanillaDelDia($ubicaciones, $companyUuid);

        return $ubicaciones;
    }

    /**
     * Obtiene la última ubicación conocida de un conductor específico.
     */
    public function getLastLocation(string $driverUuid): ?DriverLocation
    {
        $ubicacion = DriverLocation::where('third_party_uuid', $driverUuid)
            ->latest('recorded_at')
            ->with(['driver', 'vehicle', 'project'])
            ->first();

        if ($ubicacion) {
            $this->adjuntarPlanillaDelDia(collect([$ubicacion]), $ubicacion->company_uuid);
        }

        return $ubicacion;
    }

    /**
     * Adjunta a cada ubicación la planilla del día (proyecto, funcionario y rutas).
     * Cruza por vehículo (uuid directo o placa para subcontratados) y proyecto.
     * Si no hay planilla, deja planilla_dia en nulo sin romper la respuesta.
     *
     * El número de consultas NO depende de la cantidad de conductores (ARQ-007): una consulta
     * para las planillas de hoy (con todo lo que usan los accesores precargado) y, solo si algún
     * conductor no tiene planilla con rutas hoy, una única consulta de respaldo para todos ellos.
     *
     * @param  \Illuminate\Support\Collection<int, DriverLocation>  $ubicaciones
     */
    private function adjuntarPlanillaDelDia(\Illuminate\Support\Collection $ubicaciones, ?string $companyUuid): void
    {
        if ($ubicaciones->isEmpty()) {
            return;
        }

        try {
            $hoy = now()->toDateString();

            // `service_date` es DATE: comparación directa (whereDate envuelve la columna y anula el índice).
            $planillas = \App\Models\ServiceDeliveryControlSheet::query()
                ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
                ->where('service_date', $hoy)
                ->with($this->relacionesDePlanilla())
                ->get();

            /** @var \Illuminate\Support\Collection<int, DriverLocation> $necesitanRespaldo */
            $necesitanRespaldo = collect();
            /** @var array<int, array{0: object, 1: \Illuminate\Support\Collection}> $deHoySinRutas */
            $deHoySinRutas = [];

            foreach ($ubicaciones as $ubicacion) {
                $candidatas = $planillas->filter(fn ($p) => $this->planillaCorrespondeAlVehiculo($p, $ubicacion));

                // Preferir la planilla del mismo proyecto del GPS cuando hay varias.
                $elegida = null;
                if ($candidatas->isNotEmpty()) {
                    $elegida = $ubicacion->project_uuid
                        ? $candidatas->firstWhere('project_uuid', $ubicacion->project_uuid) ?? $candidatas->first()
                        : $candidatas->first();
                }

                if (! $elegida) {
                    $necesitanRespaldo->push($ubicacion);

                    continue;
                }

                $rutas = $this->rutasDePlanilla($elegida);

                // Si la planilla de hoy no trae rutas (disponibilidad), se respalda con las últimas
                // rutas conocidas del mismo vehículo/proyecto para que el monitor muestre ruta y funcionario.
                if ($rutas->isEmpty()) {
                    $necesitanRespaldo->push($ubicacion);
                    $deHoySinRutas[$ubicacion->id] = [$elegida, $rutas];

                    continue;
                }

                $ubicacion->setAttribute('planilla_dia', $this->planillaDia($elegida, $rutas, $ubicacion, true));
            }

            if ($necesitanRespaldo->isEmpty()) {
                return;
            }

            $respaldos = $this->ultimasRutasConocidas($necesitanRespaldo, $companyUuid);

            foreach ($necesitanRespaldo as $ubicacion) {
                if (isset($respaldos[$ubicacion->id])) {
                    $ubicacion->setAttribute('planilla_dia', $respaldos[$ubicacion->id]);
                } elseif (isset($deHoySinRutas[$ubicacion->id])) {
                    [$elegida, $rutas] = $deHoySinRutas[$ubicacion->id];
                    $ubicacion->setAttribute('planilla_dia', $this->planillaDia($elegida, $rutas, $ubicacion, true));
                } else {
                    $ubicacion->setAttribute('planilla_dia', null);
                }
            }
        } catch (\Throwable $e) {
            foreach ($ubicaciones as $ubicacion) {
                $ubicacion->setAttribute('planilla_dia', null);
            }
        }
    }

    /**
     * Relaciones que usa el monitor, incluidas las de los accesores `vehicle_license_plate` y
     * `driver_name` de la planilla (sin precargarlas, cada acceso era una consulta por planilla).
     *
     * @return array<int|string, mixed>
     */
    private function relacionesDePlanilla(): array
    {
        return [
            'routes' => fn ($q) => $q->where('is_active', true)->orderBy('order_index'),
            'internalControl.vehicle',
            'internalControl.thirdParty',
            'subcontractedControl',
            'project:uuid,project_name',
        ];
    }

    /** Si la planilla es del vehículo de la ubicación (uuid del control interno o placa). */
    private function planillaCorrespondeAlVehiculo(object $planilla, object $ubicacion): bool
    {
        $porVehiculo = $planilla->internalControl
            && $ubicacion->vehicle_uuid
            && $planilla->internalControl->vehicle_uuid === $ubicacion->vehicle_uuid;

        $placa = $ubicacion->vehicle?->vehicle_license_plate;
        $porPlaca = $placa && (
            $planilla->vehicle_license_plate === $placa
            || $planilla->internalControl?->vehicle?->vehicle_license_plate === $placa
        );

        return $porVehiculo || $porPlaca;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function rutasDePlanilla(object $planilla): \Illuminate\Support\Collection
    {
        return $planilla->routes->map(fn ($r) => [
            'origin' => $r->origin,
            'destination' => $r->destination,
            'funcionario_nombre' => $r->funcionario_nombre,
            'funcionario_cc' => $r->funcionario_cc,
        ])->values();
    }

    /** @return array<string, mixed> */
    private function planillaDia(object $planilla, \Illuminate\Support\Collection $rutas, object $ubicacion, bool $esHoy): array
    {
        return [
            'uuid' => $planilla->uuid,
            'service_date' => $planilla->service_date,
            'driver_name' => $planilla->driver_name,
            'project_name' => $planilla->project?->project_name ?? $ubicacion->project?->project_name,
            'routes' => $rutas,
            'es_planilla_hoy' => $esHoy,
        ];
    }

    /**
     * Para varios conductores a la vez: la planilla más reciente (hasta hoy) del mismo vehículo,
     * y del mismo proyecto si el GPS lo trae, que sí tenga rutas activas. Una sola consulta con
     * tope de filas, en lugar de una búsqueda por conductor.
     *
     * @param  \Illuminate\Support\Collection<int, DriverLocation>  $ubicaciones
     * @return array<int, array<string, mixed>> planilla_dia por id de ubicación (solo las que tienen respaldo)
     */
    private function ultimasRutasConocidas(\Illuminate\Support\Collection $ubicaciones, ?string $companyUuid): array
    {
        try {
            $vehiculos = $ubicaciones->pluck('vehicle_uuid')->filter()->unique()->values()->all();
            $placas = $ubicaciones->map(fn ($u) => $u->vehicle?->vehicle_license_plate)->filter()->unique()->values()->all();
            if (! $vehiculos && ! $placas) {
                return [];
            }

            $candidatas = \App\Models\ServiceDeliveryControlSheet::query()
                ->when($companyUuid, fn ($q) => $q->where('company_uuid', $companyUuid))
                ->where('service_date', '<=', now()->toDateString())
                ->whereHas('routes', fn ($q) => $q->where('is_active', true))
                ->where(function ($q) use ($vehiculos, $placas) {
                    $q->whereHas('internalControl', fn ($i) => $i->whereIn('vehicle_uuid', $vehiculos))
                        ->orWhereHas('internalControl.vehicle', fn ($v) => $v->whereIn('vehicle_license_plate', $placas))
                        ->orWhereHas('subcontractedControl', fn ($s) => $s->whereIn('vehicle_license_plate', $placas));
                })
                ->with($this->relacionesDePlanilla())
                ->orderByDesc('service_date')
                ->limit(500)
                ->get();

            $resultado = [];
            foreach ($ubicaciones as $ubicacion) {
                $elegida = $candidatas->first(function ($p) use ($ubicacion) {
                    if ($ubicacion->project_uuid && $p->project_uuid !== $ubicacion->project_uuid) {
                        return false;
                    }

                    return $this->planillaCorrespondeAlVehiculo($p, $ubicacion);
                });

                if ($elegida) {
                    $resultado[$ubicacion->id] = $this->planillaDia($elegida, $this->rutasDePlanilla($elegida), $ubicacion, false);
                }
            }

            return $resultado;
        } catch (\Throwable) {
            return [];
        }
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
     * Punto anterior del conductor y la empresa antes de insertar el nuevo.
     *
     * El id del punto nuevo todavía no existe y será el mayor, así que cualquier
     * registro con la misma marca de tiempo es anterior: basta el rango simple.
     * Se filtra por empresa para que la cadena de distancias no mezcle
     * recorridos de distintas empresas del mismo conductor; con el índice
     * forzado el filtro no cambia el plan.
     */
    private function puntoAnterior(string $driverUuid, ?string $companyUuid, mixed $recordedAt): ?DriverLocation
    {
        $consulta = $this->consultaVecino($driverUuid, $companyUuid);

        if ($companyUuid) {
            $consulta->where('company_uuid', $companyUuid);
        }

        return $consulta
            ->where('recorded_at', '<=', $recordedAt)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Recalcula la distancia del punto siguiente cuando el nuevo llegó desordenado.
     *
     * El orden real es (recorded_at, id): primero los puntos con marca posterior
     * y, entre los que comparten marca, los de id mayor. Si no hay siguiente, el
     * punto era el último y no hay nada que corregir. El tramo que ese punto
     * medía cambia, así que la sesión activa corrige su acumulado con la
     * diferencia para no dejarlo desfasado.
     */
    private function recalcularPuntoSiguiente(DriverLocation $location): void
    {
        $siguiente = $this->puntoSiguiente($location);

        if (! $siguiente) {
            return;
        }

        $distanciaPrevia = (float) $siguiente->distance_meters;
        $distanciaNueva = $this->distanciaEntrePuntos($location, $siguiente);

        if ($distanciaNueva === $distanciaPrevia) {
            return;
        }

        $siguiente->distance_meters = $distanciaNueva;
        $siguiente->save();

        $this->ajustarSesionActiva($siguiente->third_party_uuid, $distanciaNueva - $distanciaPrevia);
        $this->ajustarDistanciaResumenDiario($siguiente, $distanciaNueva - $distanciaPrevia);
    }

    /**
     * Suma el punto recién guardado al resumen de su día.
     *
     * La fila se bloquea dentro de la transacción para que dos puntos del mismo
     * día no se pisen entre sí. Si dos escrituras concurrentes la crean a la vez,
     * la segunda reintenta la lectura tras el conflicto de unicidad.
     */
    private function actualizarResumenDiario(DriverLocation $location): void
    {
        $resumen = $this->obtenerOCrearResumenBloqueado($location);

        $resumen->total_points++;
        $resumen->total_distance_meters = round(
            (float) $resumen->total_distance_meters + (float) $location->distance_meters,
            2
        );

        $muestras = array_map(
            fn ($arreglo) => MuestraRuta::desdeArreglo($arreglo),
            $resumen->samples ?? []
        );
        $muestras[] = MuestraRuta::desdePunto($location);

        $resumen->samples = array_map(
            fn ($muestra) => $muestra->aArreglo(),
            $this->agregarMuestra($muestras)
        );
        $resumen->save();
    }

    /**
     * Corrige la distancia del día cuando un punto tardío cambia el tramo de un
     * punto ya contabilizado. Sin fila no hay nada que corregir: ese día nunca
     * se contabilizó.
     */
    private function ajustarDistanciaResumenDiario(DriverLocation $punto, float $deltaMeters): void
    {
        if ($deltaMeters === 0.0) {
            return;
        }

        $resumen = $this->obtenerResumenBloqueado($punto);

        if (! $resumen) {
            return;
        }

        $resumen->total_distance_meters = round((float) $resumen->total_distance_meters + $deltaMeters, 2);
        $resumen->save();
    }

    /**
     * Fila del día del punto, bloqueada para escritura.
     */
    private function obtenerResumenBloqueado(DriverLocation $punto): ?DriverLocationDailyStat
    {
        return DriverLocationDailyStat::where('third_party_uuid', $punto->third_party_uuid)
            ->where('company_uuid', $punto->company_uuid)
            ->where('service_date', Carbon::parse($punto->recorded_at)->toDateString())
            ->lockForUpdate()
            ->first();
    }

    /**
     * Fila del día del punto, creándola si no existe.
     *
     * Si dos escrituras concurrentes la crean a la vez, la segunda reintenta la
     * lectura tras el conflicto de unicidad. La lectura con bloqueo espera a que
     * la escritura ganadora confirme, así que la fila ya es visible.
     */
    private function obtenerOCrearResumenBloqueado(DriverLocation $punto): DriverLocationDailyStat
    {
        $resumen = $this->obtenerResumenBloqueado($punto);

        if ($resumen) {
            return $resumen;
        }

        try {
            return DriverLocationDailyStat::create([
                'company_uuid' => $punto->company_uuid,
                'third_party_uuid' => $punto->third_party_uuid,
                'service_date' => Carbon::parse($punto->recorded_at)->toDateString(),
                'total_points' => 0,
                'total_distance_meters' => 0,
                'samples' => [],
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->obtenerResumenBloqueado($punto) ?? throw new \RuntimeException(
                'No se pudo obtener el resumen diario tras un conflicto de unicidad.'
            );
        }
    }

    /**
     * Agrega muestras manteniendo el orden cronológico y el tope, con primero
     * y último siempre presentes.
     *
     * @param  array<int, MuestraRuta>  $muestras
     * @return array<int, MuestraRuta>
     */
    private function agregarMuestra(array $muestras): array
    {
        usort($muestras, fn ($a, $b) => $a->registradaEn <=> $b->registradaEn);

        $indices = DriverLocationDailyStat::indicesParaTope(count($muestras), DriverLocationDailyStat::MUESTRAS_POR_DIA);

        return array_values(array_map(fn ($i) => $muestras[$i], $indices));
    }

    /**
     * Punto que sigue al nuevo en el orden real (recorded_at, id): con marca posterior o, a igual
     * marca, de id mayor. Una sola consulta (antes eran dos en el caso normal de punto en orden).
     */
    private function puntoSiguiente(DriverLocation $location): ?DriverLocation
    {
        return $this->consultaVecino($location->third_party_uuid, $location->company_uuid)
            ->where(function ($q) use ($location) {
                $q->where('recorded_at', '>', $location->recorded_at)
                    ->orWhere(fn ($q2) => $q2->where('recorded_at', $location->recorded_at)->where('id', '>', $location->id));
            })
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Consulta base del vecino, con el índice por conductor forzado.
     *
     * Filtra por conductor y empresa para que la cadena de distancias no mezcle
     * recorridos de distintas empresas. Con el índice forzado el filtro de
     * empresa no cambia el plan; sin el hint, el optimizador elegía el índice
     * de empresa y recorría todo su histórico en cada punto.
     */
    private function consultaVecino(string $driverUuid, ?string $companyUuid): Builder
    {
        $query = DriverLocation::withoutGlobalScope('company')
            ->from(DB::raw('driver_locations FORCE INDEX (idx_dl_driver_recorded)'))
            ->where('third_party_uuid', $driverUuid);

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query;
    }

    /**
     * Distancia en metros entre dos puntos, redondeada a centímetros.
     */
    private function distanciaEntrePuntos(DriverLocation $desde, DriverLocation $hasta): float
    {
        return round($this->haversineDistance(
            (float) $desde->latitude,
            (float) $desde->longitude,
            (float) $hasta->latitude,
            (float) $hasta->longitude
        ), 2);
    }

    /**
     * Corrige el acumulado de la sesión activa cuando la distancia de un punto
     * ya contabilizado cambia por la llegada de un punto desordenado.
     */
    private function ajustarSesionActiva(string $driverUuid, float $deltaMeters): void
    {
        if ($deltaMeters === 0.0) {
            return;
        }

        DriverLocationSession::where('third_party_uuid', $driverUuid)
            ->where('status', 'active')
            ->increment('total_distance_km', round($deltaMeters / 1000, 4));
    }

    /**
     * Actualiza la sesión activa con la nueva ubicación recibida.
     *
     * La distancia usa la ya calculada para el punto, sin repetir la consulta
     * del vecino anterior.
     */
    private function updateActiveSession(DriverLocation $location): void
    {
        $distanceKm = round(((float) ($location->distance_meters ?? 0)) / 1000, 4);

        // Un solo UPDATE atómico (antes: SELECT + 2 incrementos por punto).
        DriverLocationSession::where('third_party_uuid', $location->third_party_uuid)
            ->where('status', 'active')
            ->update([
                'total_distance_km' => DB::raw('total_distance_km + '.$distanceKm),
                'total_points' => DB::raw('total_points + 1'),
            ]);
    }

    /**
     * Procesa todas las geocercas activas para la empresa y genera alertas.
     *
     * El estado previo sale del punto anterior que ya se consultó al guardar (no una consulta por
     * geocerca y por tipo de alerta): sin punto anterior se considera que estaba fuera.
     */
    private function processGeofences(DriverLocation $location, ?DriverLocation $anterior = null): void
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
                $wasInside = $anterior ? $this->isPointInsideGeofence($anterior, $geofence) : false;

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
                $wasInside = $anterior ? $this->isPointInsideGeofence($anterior, $geofence) : false;

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