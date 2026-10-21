<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estado de combustible por vehículo para el Control de Combustible y Kilometraje.
 *
 * Cruza el último tanqueo con el kilometraje real (preoperacional diario o
 * kilometraje final de la planilla) para mostrar cuánto se ha recorrido, si
 * se superó el alcance estimado y cuántos kilómetros faltan.
 *
 * Línea de base:
 *   1. El odómetro final del último tanqueo, si se registró.
 *   2. Si el tanqueo no trae odómetro, se usa el preoperacional más cercano a
 *      la fecha del tanqueo (el último anterior o, si no hay, el primero
 *      posterior). La fila se marca con origen "preoperacional" para no
 *      confundirla con una lectura tomada en el tanqueo.
 *
 * El kilometraje actual es el mayor entre el preoperacional y la planilla,
 * igual que el pronóstico de mantenimiento.
 */
class FuelStatusService
{
    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     summary: array{total: int, excedidos: int, proximos: int, sin_datos: int, sin_tanqueo: int, ok: int},
     *     projects: array<int, array{uuid: string, name: string}>
     * }
     */
    public function fleetStatus(?string $companyUuid = null, ?string $thirdPartyUuid = null, int $limit = 500): array
    {
        $vehiculosQuery = Vehicle::query()
            ->where('vehicles.is_active', true)
            ->select(['uuid', 'company_uuid', 'vehicle_license_plate', 'internal_number']);

        if ($companyUuid) {
            $vehiculosQuery->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $vehiculosQuery->where('third_party_uuid', $thirdPartyUuid);
        }

        $porcentajeAviso = (float) config('fuel.notification_warning_percent', 85);
        $reservaKm = (float) config('fuel.notification_reserve_km', 50);
        $items = [];

        $vehiculosQuery->chunk(100, function ($vehiculos) use (&$items, $porcentajeAviso, $reservaKm) {
            $uuids = $vehiculos->pluck('uuid')->all();

            // Último tanqueo por vehículo (orden ascendente + keyBy deja el más reciente).
            $tanqueos = DB::table('fuel_logs')
                ->whereIn('vehicle_uuid', $uuids)
                ->orderBy('date')
                ->orderBy('id')
                ->get(['vehicle_uuid', 'uuid', 'date', 'final_odometer', 'estimated_range_km', 'average_performance', 'gallons_pumped', 'fuel_price_per_gallon', 'total_spent', 'cost_per_km', 'created_at'])
                ->keyBy('vehicle_uuid');

            // Preoperacionales del lote, ordenados por fecha: sirven para el km
            // actual y para la línea de base cuando el tanqueo no trae odómetro.
            $inspecciones = DB::table('vehicle_inspections')
                ->whereIn('vehicle_uuid', $uuids)
                ->whereNotNull('mileage')
                ->orderBy('inspection_date')
                ->orderBy('id')
                ->get(['vehicle_uuid', 'inspection_date', 'mileage', 'created_at'])
                ->groupBy('vehicle_uuid');

            // Kilometraje final de la planilla (el mayor por vehículo).
            $planillas = DB::table('service_delivery_control_sheet as sdcs')
                ->join('service_internal_controls as sic', 'sic.service_delivery_control_sheet_uuid', '=', 'sdcs.uuid')
                ->whereIn('sic.vehicle_uuid', $uuids)
                ->whereNotNull('sdcs.ending_kilometer')
                ->orderByRaw('cast(sdcs.ending_kilometer as unsigned)')
                ->get(['sic.vehicle_uuid as vehicle_uuid', 'sdcs.service_date', 'sdcs.ending_kilometer'])
                ->keyBy('vehicle_uuid');

            // Proyectos activos por vehículo (para el filtro del modal).
            $proyectosPorVehiculo = DB::table('project_driver_vehicles as pdv')
                ->join('projects as p', 'p.uuid', '=', 'pdv.project_uuid')
                ->whereIn('pdv.vehicle_uuid', $uuids)
                ->where('pdv.is_active', 1)
                ->get(['pdv.vehicle_uuid', 'p.uuid as project_uuid', 'p.project_name'])
                ->groupBy('vehicle_uuid');

            foreach ($vehiculos as $vehiculo) {
                $insp = $inspecciones->get($vehiculo->uuid, collect());
                $ultimaInspeccion = $insp->sortByDesc('mileage')->first();
                $planilla = $planillas->get($vehiculo->uuid);

                $proyectos = ($proyectosPorVehiculo->get($vehiculo->uuid) ?? collect())
                    ->map(fn ($p) => ['uuid' => $p->project_uuid, 'name' => $p->project_name])
                    ->unique('uuid')
                    ->values()
                    ->all();

                // Kilometraje actual: el mayor entre preoperacional y planilla.
                $kmActual = 0;
                $fuenteActual = null;
                $fechaActual = null;
                if ($ultimaInspeccion) {
                    $kmActual = (int) $ultimaInspeccion->mileage;
                    $fuenteActual = 'preoperacional';
                    $fechaActual = (string) $ultimaInspeccion->inspection_date;
                }
                if ($planilla && (int) $planilla->ending_kilometer > $kmActual) {
                    $kmActual = (int) $planilla->ending_kilometer;
                    $fuenteActual = 'planilla';
                    $fechaActual = (string) $planilla->service_date;
                }

                $tanqueo = $tanqueos->get($vehiculo->uuid);

                // Vehículo sin tanqueos: se lista para que la flota completa sea visible.
                if (! $tanqueo) {
                    $items[] = [
                        'vehicle_uuid' => $vehiculo->uuid,
                        'vehicle_license_plate' => $vehiculo->vehicle_license_plate,
                        'internal_number' => $vehiculo->internal_number,
                        'projects' => $proyectos,
                        'refuel' => null,
                        'baseline' => ['odometer' => null, 'date' => null, 'source' => null],
                        'current' => [
                            'odometer' => $kmActual > 0 ? $kmActual : null,
                            'date' => $fechaActual,
                            'source' => $fuenteActual,
                        ],
                        'km_traveled' => null,
                        'km_limit' => null,
                        'km_remaining' => null,
                        'percentage' => null,
                        'fuel' => null,
                        'status' => 'SIN_TANQUEO',
                        'message' => 'Sin tanqueo registrado.',
                    ];

                    continue;
                }

                // Línea de base: odómetro del tanqueo o, si falta, preoperacional cercano.
                $kmBase = null;
                $fuenteBase = null;
                $fechaBase = null;
                if ($tanqueo->final_odometer !== null) {
                    $kmBase = (int) $tanqueo->final_odometer;
                    $fuenteBase = 'tanqueo';
                    $fechaBase = (string) $tanqueo->date;
                } elseif ($insp->isNotEmpty()) {
                    $fechaTanqueo = (string) $tanqueo->date;
                    $momentoTanqueo = $tanqueo->created_at
                        ? Carbon::parse((string) $tanqueo->created_at)
                        : Carbon::parse($fechaTanqueo.' 12:00:00');

                    // Lecturas anteriores al tanqueo (misma fecha: por hora de registro).
                    $anteriores = $insp->filter(function ($i) use ($fechaTanqueo, $momentoTanqueo) {
                        $fecha = (string) $i->inspection_date;
                        if ($fecha < $fechaTanqueo) {
                            return true;
                        }
                        if ($fecha > $fechaTanqueo) {
                            return false;
                        }

                        $momento = $i->created_at
                            ? Carbon::parse((string) $i->created_at)
                            : Carbon::parse($fecha.' 00:00:00');

                        return $momento <= $momentoTanqueo;
                    });

                    // Sin lectura previa: la primera posterior al tanqueo es la base
                    // (no la última del día, que dejaría el recorrido en cero).
                    $base = $anteriores->last() ?? $insp->first();
                    $kmBase = (int) $base->mileage;
                    $fuenteBase = 'preoperacional';
                    $fechaBase = (string) $base->inspection_date;
                }

                $limite = (float) ($tanqueo->estimated_range_km ?? 0);
                $recorrido = null;
                $restante = null;
                $porcentaje = null;
                $estado = 'SIN_DATOS';
                $mensaje = 'Sin odómetro suficiente para calcular el recorrido.';

                if ($kmBase !== null && $kmActual > 0 && $limite > 0) {
                    if ($kmActual < $kmBase) {
                        $mensaje = 'El odómetro actual es menor al del tanqueo; revise las lecturas.';
                    } else {
                        $recorrido = $kmActual - $kmBase;
                        $restante = round($limite - $recorrido, 2);
                        $porcentaje = round(($recorrido / $limite) * 100, 1);

                        if ($restante <= 0) {
                            $estado = 'EXCEDIDO';
                            $mensaje = 'Excedido por '.round(abs($restante)).' km. Tanquear.';
                        } elseif ($restante <= $reservaKm || $porcentaje >= $porcentajeAviso) {
                            $estado = 'PROXIMO';
                            $mensaje = 'Restan '.round($restante).' km para el alcance estimado.';
                        } else {
                            $estado = 'OK';
                            $mensaje = 'Restan '.round($restante).' km.';
                        }
                    }
                }

                $items[] = [
                    'vehicle_uuid' => $vehiculo->uuid,
                    'vehicle_license_plate' => $vehiculo->vehicle_license_plate,
                    'internal_number' => $vehiculo->internal_number,
                    'projects' => $proyectos,
                    'refuel' => [
                        'uuid' => $tanqueo->uuid,
                        'date' => (string) $tanqueo->date,
                        'odometer' => $tanqueo->final_odometer !== null ? (int) $tanqueo->final_odometer : null,
                        'estimated_range_km' => $limite > 0 ? round($limite, 2) : null,
                        'performance' => $tanqueo->average_performance !== null ? (float) $tanqueo->average_performance : null,
                    ],
                    'baseline' => [
                        'odometer' => $kmBase,
                        'date' => $fechaBase,
                        'source' => $fuenteBase,
                    ],
                    'current' => [
                        'odometer' => $kmActual > 0 ? $kmActual : null,
                        'date' => $fechaActual,
                        'source' => $fuenteActual,
                    ],
                    'km_traveled' => $recorrido,
                    'km_limit' => $limite > 0 ? round($limite, 2) : null,
                    'km_remaining' => $restante,
                    'percentage' => $porcentaje,
                    'fuel' => $this->combustible($tanqueo, $recorrido),
                    'status' => $estado,
                    'message' => $mensaje,
                ];
            }
        });

        // Primero lo que excedió el tope y luego lo que está por agotarse.
        $orden = ['EXCEDIDO' => 0, 'PROXIMO' => 1, 'SIN_DATOS' => 2, 'OK' => 3, 'SIN_TANQUEO' => 4];
        usort($items, function (array $a, array $b) use ($orden) {
            $porEstado = $orden[$a['status']] <=> $orden[$b['status']];
            if ($porEstado !== 0) {
                return $porEstado;
            }

            return ($b['percentage'] ?? -1) <=> ($a['percentage'] ?? -1);
        });

        $items = array_slice($items, 0, max(1, $limit));

        $summary = [
            'total' => count($items),
            'excedidos' => count(array_filter($items, fn ($i) => $i['status'] === 'EXCEDIDO')),
            'proximos' => count(array_filter($items, fn ($i) => $i['status'] === 'PROXIMO')),
            'sin_datos' => count(array_filter($items, fn ($i) => $i['status'] === 'SIN_DATOS')),
            'sin_tanqueo' => count(array_filter($items, fn ($i) => $i['status'] === 'SIN_TANQUEO')),
            'ok' => count(array_filter($items, fn ($i) => $i['status'] === 'OK')),
        ];

        // Catálogo de proyectos presentes en la respuesta (para el filtro del modal).
        $proyectos = [];
        foreach ($items as $item) {
            foreach ($item['projects'] ?? [] as $proyecto) {
                $proyectos[$proyecto['uuid']] = $proyecto['name'];
            }
        }
        $catalogoProyectos = [];
        foreach ($proyectos as $uuid => $nombre) {
            $catalogoProyectos[] = ['uuid' => $uuid, 'name' => $nombre];
        }
        usort($catalogoProyectos, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['items' => array_values($items), 'summary' => $summary, 'projects' => $catalogoProyectos];
    }

    /**
     * Combustible del último tanqueo: galones surtidos, consumidos según el
     * kilometraje (preoperacional/planilla) y saldo estimado, en galones y dinero.
     *
     *   consumidos = km recorridos / rendimiento (km/gal)
     *   saldo      = galones surtidos - consumidos
     *   dinero     = galones consumidos × precio por galón
     *
     * @return array{
     *     gallons_pumped: float,
     *     gallons_consumed: float|null,
     *     gallons_remaining: float|null,
     *     performance: float|null,
     *     price_per_gallon: float|null,
     *     money_spent: float,
     *     money_consumed: float|null,
     *     money_remaining: float|null
     * }
     */
    private function combustible(object $tanqueo, ?int $recorrido): array
    {
        $surtidos = (float) ($tanqueo->gallons_pumped ?? 0);
        $rendimiento = (float) ($tanqueo->average_performance ?? 0);
        $precioGalon = (float) ($tanqueo->fuel_price_per_gallon ?? 0);
        $totalTanqueo = (float) ($tanqueo->total_spent ?? 0);
        $costoKm = (float) ($tanqueo->cost_per_km ?? 0);

        $consumidosExactos = ($recorrido !== null && $rendimiento > 0)
            ? $recorrido / $rendimiento
            : null;
        $consumidos = $consumidosExactos !== null ? round($consumidosExactos, 2) : null;

        $dineroConsumido = null;
        if ($recorrido !== null) {
            if ($precioGalon > 0 && $consumidosExactos !== null) {
                $dineroConsumido = round($consumidosExactos * $precioGalon, 2);
            } elseif ($costoKm > 0) {
                $dineroConsumido = round($recorrido * $costoKm, 2);
            }
        }

        return [
            'gallons_pumped' => round($surtidos, 2),
            'gallons_consumed' => $consumidos,
            'gallons_remaining' => $consumidos !== null ? round($surtidos - $consumidos, 2) : null,
            'performance' => $rendimiento > 0 ? round($rendimiento, 2) : null,
            'price_per_gallon' => $precioGalon > 0 ? round($precioGalon, 2) : null,
            'money_spent' => round($totalTanqueo, 2),
            'money_consumed' => $dineroConsumido,
            'money_remaining' => $dineroConsumido !== null
                ? round($totalTanqueo - $dineroConsumido, 2)
                : null,
        ];
    }
}
