<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\Vehicle;
use App\Services\Fleet\Combustible\EstadoCombustible;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fachada del estado de combustible por vehículo (Control de Combustible y
 * Kilometraje).
 *
 * Toda la lógica de cálculo vive en el módulo EstadoCombustible (issue #8);
 * esta clase solo arma los listados: consulta los vehículos por lotes de 100,
 * carga las lecturas del lote con el módulo y aplica la regla única de
 * agotamiento (EXCEDIDO / PROXIMO / OK / SIN_DATOS) sobre el último tanqueo.
 *
 * Contrato conservado de la API (GET /fuel-logs/status): items, summary y
 * catálogo de proyectos.
 */
class FuelStatusService
{
    public function __construct(private readonly EstadoCombustible $estadoCombustible) {}

    /** @return array{porcentaje: float, reserva_km: float} */
    public function umbralAgotamiento(): array
    {
        return $this->estadoCombustible->umbralAgotamiento();
    }

    /**
     * Estado de combustible de toda la flota (contrato del endpoint
     * /fuel-logs/status).
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     summary: array{total: int, excedidos: int, proximos: int, sin_datos: int, sin_tanqueo: int, ok: int},
     *     projects: array<int, array{uuid: string, name: string}>
     * }
     */
    public function estadoDeFlota(?string $companyUuid = null, ?string $thirdPartyUuid = null, int $limit = 500): array
    {
        return $this->armarEstado($companyUuid, $thirdPartyUuid, null, $limit);
    }

    /**
     * Estado de combustible de un vehículo. Null si no existe o está inactivo.
     *
     * @return ?array<string, mixed>
     */
    public function estadoDeVehiculo(string $vehicleUuid): ?array
    {
        $estado = $this->armarEstado(null, null, $vehicleUuid, 1);

        return $estado['items'][0] ?? null;
    }

    /**
     * Vehículos con el tanqueo próximo a agotarse o ya excedido, con la MISMA
     * regla que la pantalla (item estado EXCEDIDO / PROXIMO). Detalle del
     * avisos: estado + números; los textos de la notificación los arma
     * NotificationsService con sus plantillas, a partir de estos números.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alertasDeAgotamiento(?string $companyUuid = null, ?string $thirdPartyUuid = null): array
    {
        $vehiculosQuery = Vehicle::query()
            ->where('vehicles.is_active', true)
            ->select(['uuid', 'company_uuid', 'vehicle_license_plate']);

        if ($companyUuid) {
            $vehiculosQuery->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $vehiculosQuery->where('third_party_uuid', $thirdPartyUuid);
        }

        $alertas = [];

        $vehiculosQuery->chunk(100, function ($vehiculos) use (&$alertas) {
            $uuids = $vehiculos->pluck('uuid')->all();

            $tanqueos = $this->estadoCombustible->tanqueosDeLote($uuids);
            $inspecciones = $this->estadoCombustible->inspeccionesDeLote($uuids);
            $planillas = $this->estadoCombustible->planillasDeLote($uuids);

            foreach ($vehiculos as $vehiculo) {
                $tanqueo = $tanqueos->get($vehiculo->uuid);
                if (! $tanqueo) {
                    continue;
                }

                $limite = (float) ($tanqueo->estimated_range_km ?? 0);
                $lecturas = $inspecciones->get($vehiculo->uuid);
                $actual = $this->estadoCombustible->kilometrajeActual(
                    $lecturas !== null ? $lecturas->sortByDesc('mileage')->first() : null,
                    $planillas->get($vehiculo->uuid)
                );
                $base = $this->estadoCombustible->lineaBase($tanqueo, $lecturas);

                $clasificacion = $this->estadoCombustible->clasificarAgotamiento(
                    $base['odometer'],
                    $actual['odometer'],
                    $limite
                );

                if (! in_array($clasificacion['estado'], ['EXCEDIDO', 'PROXIMO'], true)) {
                    continue;
                }

                $alertas[] = [
                    'vehicle_uuid' => $vehiculo->uuid,
                    'vehicle_license_plate' => $vehiculo->vehicle_license_plate,
                    'company_uuid' => $vehiculo->company_uuid,
                    'refuel_uuid' => $tanqueo->uuid,
                    'refuel_date' => $tanqueo->date ? Carbon::parse((string) $tanqueo->date)->format('Y-m-d') : 'sin fecha',
                    'km_since_refuel' => $clasificacion['recorrido'] ?? 0,
                    'estimated_range_km' => round($limite, 2),
                    'km_remaining' => $clasificacion['restante'],
                    'percentage' => $clasificacion['porcentaje'],
                    'estado' => $clasificacion['estado'],
                ];
            }
        });

        return $alertas;
    }

    /**
     * Pipeline único: recorre la flota por lotes, carga las lecturas del lote
     * con el módulo y arma cada ítem con la regla compartida.
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     summary: array{total: int, excedidos: int, proximos: int, sin_datos: int, sin_tanqueo: int, ok: int},
     *     projects: array<int, array{uuid: string, name: string}>
     * }
     */
    private function armarEstado(?string $companyUuid, ?string $thirdPartyUuid, ?string $vehicleUuid, int $limit): array
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

        if ($vehicleUuid) {
            $vehiculosQuery->where('uuid', $vehicleUuid);
        }

        $items = [];

        $vehiculosQuery->chunk(100, function ($vehiculos) use (&$items) {
            $uuids = $vehiculos->pluck('uuid')->all();

            $tanqueos = $this->estadoCombustible->tanqueosDeLote($uuids);
            $inspecciones = $this->estadoCombustible->inspeccionesDeLote($uuids);
            $planillas = $this->estadoCombustible->planillasDeLote($uuids);
            $proyectosPorVehiculo = $this->estadoCombustible->proyectosDeLote($uuids);

            foreach ($vehiculos as $vehiculo) {
                $lecturas = $inspecciones->get($vehiculo->uuid);
                $ultimaLectura = $lecturas !== null ? $lecturas->sortByDesc('mileage')->first() : null;
                $planilla = $planillas->get($vehiculo->uuid);

                $proyectos = ($proyectosPorVehiculo->get($vehiculo->uuid) ?? collect())
                    ->map(fn ($p) => ['uuid' => $p->project_uuid, 'name' => $p->project_name])
                    ->unique('uuid')
                    ->values()
                    ->all();

                $actual = $this->estadoCombustible->kilometrajeActual($ultimaLectura, $planilla);

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
                            'odometer' => $actual['odometer'] > 0 ? $actual['odometer'] : null,
                            'date' => $actual['date'],
                            'source' => $actual['source'],
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

                $base = $this->estadoCombustible->lineaBase($tanqueo, $lecturas);
                $limite = (float) ($tanqueo->estimated_range_km ?? 0);
                $clasificacion = $this->estadoCombustible->clasificarAgotamiento(
                    $base['odometer'],
                    $actual['odometer'],
                    $limite
                );

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
                    'baseline' => $base,
                    'current' => [
                        'odometer' => $actual['odometer'] > 0 ? $actual['odometer'] : null,
                        'date' => $actual['date'],
                        'source' => $actual['source'],
                    ],
                    'km_traveled' => $clasificacion['recorrido'],
                    'km_limit' => $limite > 0 ? round($limite, 2) : null,
                    'km_remaining' => $clasificacion['restante'],
                    'percentage' => $clasificacion['porcentaje'],
                    'fuel' => $this->estadoCombustible->combustibleDelTanqueo($tanqueo, $clasificacion['recorrido']),
                    'status' => $clasificacion['estado'],
                    'message' => $clasificacion['mensaje'],
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
}
