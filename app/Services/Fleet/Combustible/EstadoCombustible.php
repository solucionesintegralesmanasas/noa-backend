<?php

declare(strict_types=1);

namespace App\Services\Fleet\Combustible;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Módulo único de estado de combustible (issue #8).
 *
 * Concentra en un solo lugar TODAS las fórmulas del dominio:
 *
 *  - Redondeo financiero "half to even" (bancario) a 2 decimales.
 *  - Cálculo de la fila de tanqueo: recorrido, galones surtidos, consumo
 *    estimado por ralentí, saldo, km/gal y costo/km.
 *  - Cadena de origen del rendimiento (km/gal): manual -> registro ->
 *    promedio histórico -> tabla por cilindraje.
 *  - Kilometraje actual: el mayor entre el preoperacional y el kilometraje
 *    final de la planilla (igual que el pronóstico de mantenimiento).
 *  - Línea de base del recorrido: odómetro del tanqueo o, si falta, el
 *    preoperacional más cercano a la fecha del tanqueo.
 *  - Umbrales de configuración (porcentaje de aviso y reserva en km) y la
 *    clasificación única de agotamiento: EXCEDIDO / PROXIMO / OK / SIN_DATOS.
 *
 * Consumeros:
 *  - FuelLogService conserva la escritura en cascada (recalcularFlujo) y
 *    delega aquí el cálculo de cada fila.
 *  - FuelStatusService arma el contrato de la API (/fuel-logs/status) y los
 *    listados del modal con estas reglas.
 *  - NotificationsService detecta los avisos de agotamiento con la misma
 *    regla que la pantalla (mismos umbrales, misma línea de base).
 *
 * Las consultas por lote (más cercanas al volumen) también viven aquí, para
 * que pantalla y avisos lean los mismos datos con el mismo patrón de
 * consultas por chunk de 100 vehículos.
 */
class EstadoCombustible
{
    /** Promedio histórico (km/gal): km/gal medido o, si no hay medición, rendimiento manual. */
    private const PROMEDIO_HISTORICO_SQL = "AVG(CASE WHEN km_per_gallon > 0 THEN km_per_gallon WHEN performance_source = 'manual' THEN average_performance END) as promedio";

    // ─── Umbrales y clasificación ──────────────────────────────────────────

    /**
     * Umbrales de aviso de agotamiento (config una sola vez, para todos).
     *
     * @return array{porcentaje: float, reserva_km: float}
     */
    public function umbralAgotamiento(): array
    {
        return [
            'porcentaje' => (float) config('fuel.notification_warning_percent', 85),
            'reserva_km' => (float) config('fuel.notification_reserve_km', 50),
        ];
    }

    /**
     * Clasificación única del estado de agotamiento con la regla compartida
     * por la pantalla y los avisos.
     *
     *   recorrido  = kmActual - kmBase
     *   restante   = limite - recorrido
     *   porcentaje = recorrido / limite × 100
     *
     * EXCEDIDO: el tanque ya no alcanza (restante <= 0).
     * PROXIMO: resta menos que la reserva o se superó el porcentaje de aviso.
     * OK: dentro del alcance estimado.
     * SIN_DATOS: sin línea de base, sin kilometraje actual, sin alcance o con
     * lecturas inconsistentes (el odómetro actual es menor al del tanqueo).
     *
     * @return array{estado: string, mensaje: string, recorrido: ?int, restante: ?float, porcentaje: ?float}
     */
    public function clasificarAgotamiento(?int $kmBase, ?int $kmActual, float $limite): array
    {
        $recorrido = null;
        $restante = null;
        $porcentaje = null;
        $estado = 'SIN_DATOS';
        $mensaje = 'Sin odómetro suficiente para calcular el recorrido.';

        if ($kmBase !== null && $kmActual !== null && $kmActual > 0 && $limite > 0) {
            if ($kmActual < $kmBase) {
                $mensaje = 'El odómetro actual es menor al del tanqueo; revise las lecturas.';
            } else {
                $recorrido = $kmActual - $kmBase;
                $restante = round($limite - $recorrido, 2);
                $porcentaje = round(($recorrido / $limite) * 100, 1);

                $umbral = $this->umbralAgotamiento();

                if ($restante <= 0) {
                    $estado = 'EXCEDIDO';
                    $mensaje = 'Excedido por '.round(abs($restante)).' km. Tanquear.';
                } elseif ($restante <= $umbral['reserva_km'] || $porcentaje >= $umbral['porcentaje']) {
                    $estado = 'PROXIMO';
                    $mensaje = 'Restan '.round($restante).' km para el alcance estimado.';
                } else {
                    $estado = 'OK';
                    $mensaje = 'Restan '.round($restante).' km.';
                }
            }
        }

        return [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'recorrido' => $recorrido,
            'restante' => $restante,
            'porcentaje' => $porcentaje,
        ];
    }

    // ─── Kilometraje y línea de base ───────────────────────────────────────

    /**
     * Kilometraje actual: el mayor entre la lectura del preoperacional y el
     * kilometraje final de la planilla (igual que el pronóstico de
     * mantenimiento). Cada fila ya llega como "la mayor" de su fuente.
     *
     * @param  ?object  $inspeccion  fila con inspection_date y mileage
     * @param  ?object  $planilla    fila con service_date y ending_kilometer
     * @return array{odometer: int, date: ?string, source: ?string}
     */
    public function kilometrajeActual(?object $inspeccion, ?object $planilla): array
    {
        $kmActual = 0;
        $fuente = null;
        $fecha = null;

        if ($inspeccion !== null) {
            $kmActual = (int) $inspeccion->mileage;
            $fuente = 'preoperacional';
            $fecha = (string) $inspeccion->inspection_date;
        }

        if ($planilla !== null && (int) $planilla->ending_kilometer > $kmActual) {
            $kmActual = (int) $planilla->ending_kilometer;
            $fuente = 'planilla';
            $fecha = (string) $planilla->service_date;
        }

        return [
            'odometer' => $kmActual,
            'date' => $fecha,
            'source' => $fuente,
        ];
    }

    /**
     * Línea de base del recorrido, con regla única:
     *
     *   1. El odómetro final del tanqueo, si se registró.
     *   2. Si el tanqueo no trae odómetro, el preoperacional más cercano a la
     *      fecha del tanqueo (el último anterior o, si no hay, el primero
     *      posterior). La fuente queda como "preoperacional" para no
     *      confundirla con una lectura tomada en el tanqueo.
     *
     * Sin lecturas: no hay línea de base (recorrido no calculable).
     *
     * @param  object  $tanqueo       fila con date, created_at y final_odometer
     * @param  ?Collection  $inspecciones  lecturas del vehículo con odómetro,
     *                                     ordenadas por fecha
     * @return array{odometer: ?int, date: ?string, source: ?string}
     */
    public function lineaBase(object $tanqueo, ?Collection $inspecciones): array
    {
        if ($tanqueo->final_odometer !== null) {
            return [
                'odometer' => (int) $tanqueo->final_odometer,
                'date' => (string) $tanqueo->date,
                'source' => 'tanqueo',
            ];
        }

        if ($inspecciones === null || $inspecciones->isEmpty()) {
            return ['odometer' => null, 'date' => null, 'source' => null];
        }

        $fechaTanqueo = (string) $tanqueo->date;
        $momentoTanqueo = $tanqueo->created_at
            ? Carbon::parse((string) $tanqueo->created_at)
            : Carbon::parse($fechaTanqueo.' 12:00:00');

        // Lecturas anteriores al tanqueo (misma fecha: por hora de registro).
        $anteriores = $inspecciones->filter(function ($i) use ($fechaTanqueo, $momentoTanqueo) {
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
        $base = $anteriores->last() ?? $inspecciones->first();

        return [
            'odometer' => (int) $base->mileage,
            'date' => (string) $base->inspection_date,
            'source' => 'preoperacional',
        ];
    }

    // ─── Combustible del tanqueo ───────────────────────────────────────────

    /**
     * Combustible del último tanqueo: galones surtidos, consumidos según el
     * kilometraje (preoperacional/planilla) y saldo estimado, en galones y
     * dinero.
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
    public function combustibleDelTanqueo(object $tanqueo, ?int $recorrido): array
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

    // ─── Fila de tanqueo (escritura) ───────────────────────────────────────

    /**
     * Calcula los campos derivados de un registro de tanqueo.
     *
     * - Kilometraje opcional: si no llegan los odómetros, recorrido/km-gal/
     *   costo-km quedan vacíos (null/0) y no se rompe el cálculo.
     * - gallons_consumed es el consumo ESTIMADO por ralentí según cilindraje.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, float|int|null>
     */
    public function calcular(array $datos): array
    {
        $inicial = $datos['initial_odometer'] ?? null;
        $final = $datos['final_odometer'] ?? null;

        $recorrido = null;
        if ($inicial !== null && $final !== null && (int) $final > (int) $inicial) {
            $recorrido = (int) $final - (int) $inicial;
        }

        $gasto = $this->redondear((float) ($datos['total_spent'] ?? 0));
        $precio = $this->redondear((float) ($datos['fuel_price_per_gallon'] ?? 0));

        $galones = $precio > 0 ? $this->redondear($gasto / $precio) : 0.0;

        // Consumo estimado por ralentí (cilindraje × factor × horas / litros por galón).
        $consumido = $this->consumoEstimadoGalones([
            'displacement_liters' => (float) ($datos['displacement_liters'] ?? 0),
            'idle_factor' => (float) ($datos['idle_factor'] ?? 0),
            'idle_hours' => (float) ($datos['idle_hours'] ?? 0),
        ]);

        $saldoInicial = $this->redondear((float) ($datos['initial_balance'] ?? 0));
        $saldoFinal = $this->redondear($saldoInicial + $galones - $consumido);

        // Métricas por distancia: solo si hay recorrido.
        $kmGalon = ($galones > 0 && $recorrido !== null) ? $this->redondear($recorrido / $galones) : null;
        $costoKm = ($recorrido !== null && $recorrido > 0) ? $this->redondear($gasto / $recorrido) : 0.0;

        return [
            'recorrido' => $recorrido,
            'gallons_pumped' => $galones,
            'gallons_consumed' => $consumido,
            'final_balance' => $saldoFinal,
            'km_per_gallon' => $kmGalon,
            'cost_per_km' => $costoKm,
        ];
    }

    /**
     * Datos del vehículo necesarios para estimar el consumo por ralentí.
     *
     * @return array{engine_displacement: string|null, fuel_type: string, displacement_liters: float, idle_factor: float, idle_hours: float}
     */
    public function datosDeConsumo(?string $vehicleUuid): array
    {
        $cilindraje = null;
        $fuelType = 'OTRO';

        if ($vehicleUuid) {
            // Con scopes: un vehículo de otra empresa/sede no aporta datos.
            $vehiculo = Vehicle::query()
                ->where('uuid', $vehicleUuid)
                ->first(['engine_displacement', 'fuel_type']);

            if ($vehiculo) {
                $cilindraje = $vehiculo->engine_displacement;
                $fuelType = strtoupper((string) ($vehiculo->fuel_type ?? 'OTRO'));
            }
        }

        $cc = (float) preg_replace('/[^0-9.]/', '', (string) ($cilindraje ?? ''));
        // Si el valor es pequeño se asume que ya viene en litros (p. ej. "2.0").
        $litros = $cc >= 50 ? $cc / 1000 : $cc;

        $factores = (array) config('fuel.idle_factor_l_per_hour_per_liter', []);
        $factor = (float) ($factores[$fuelType] ?? $factores['OTRO'] ?? 0.50);

        return [
            'engine_displacement' => $cilindraje !== null && $cilindraje !== '' ? (string) $cilindraje : null,
            'fuel_type' => $fuelType,
            'displacement_liters' => round($litros, 3),
            'idle_factor' => $factor,
            'idle_hours' => 0.0,
        ];
    }

    /**
     * Galones estimados consumidos en ralentí:
     *   litros_motor × factor (L/h por L) × horas / litros_por_galón.
     *
     * @param  array{displacement_liters: float, idle_factor: float, idle_hours: float}  $vehiculo
     */
    public function consumoEstimadoGalones(array $vehiculo): float
    {
        $litrosMotor = (float) ($vehiculo['displacement_liters'] ?? 0);
        $factor = (float) ($vehiculo['idle_factor'] ?? 0);
        $horas = (float) ($vehiculo['idle_hours'] ?? 0);

        if ($litrosMotor <= 0 || $factor <= 0 || $horas <= 0) {
            return 0.0;
        }

        $litros = $litrosMotor * $factor * $horas;
        $galones = $litros / (float) config('fuel.liters_per_gallon', 3.785411784);

        return $this->redondear($galones);
    }

    // ─── Rendimiento (km/gal) ──────────────────────────────────────────────

    /**
     * Cadena de origen del rendimiento aplicado a una fila (recorrido y
     * métricas derivadas):
     *
     *   manual -> registro (km/gal de la propia fila) -> histórico del
     *   vehículo -> tabla por cilindraje; sin dato calculable, null.
     *
     * Los valores perezosos (histórico y tabla) solo se consultan si la
     * cadena llega hasta ellos; así cada llamador deja la consulta de base de
     * datos del lado correcto (con sus scopes de empresa y sede).
     *
     * @param  ?float  $manual     rendimiento fijado por el usuario en la fila
     * @param  ?float  $propio     km/gal medido en la propia fila
     * @param  ?callable(): ?float  $historico  promedio histórico del vehículo
     * @param  ?callable(): ?float  $tabla      rendimiento por cilindraje
     * @return array{valor: ?float, origen: string}
     */
    public function rendimientoDeCadena(?float $manual, ?float $propio, ?callable $historico, ?callable $tabla): array
    {
        if ($manual !== null && $manual > 0) {
            return ['valor' => $this->redondear($manual), 'origen' => 'manual'];
        }

        if ($propio !== null && $propio > 0) {
            return ['valor' => $this->redondear($propio), 'origen' => 'registro'];
        }

        if ($historico) {
            $promedio = $historico();
            if ($promedio !== null && $promedio > 0) {
                return ['valor' => $this->redondear($promedio), 'origen' => 'historico'];
            }
        }

        if ($tabla) {
            $valorTabla = $tabla();
            if ($valorTabla !== null) {
                return ['valor' => $this->redondear($valorTabla), 'origen' => 'cilindraje'];
            }
        }

        return ['valor' => null, 'origen' => 'sin_dato'];
    }

    /**
     * Promedio histórico (km/gal) del vehículo sobre un builder YA acotado
     * por los scopes del llamador (empresa/sede). Incluye el km/gal medido y,
     * cuando no hay medición, el rendimiento manual.
     */
    public function promedioHistorico(Builder $base, ?string $excluirUuid = null): ?float
    {
        $query = $base->selectRaw(self::PROMEDIO_HISTORICO_SQL);

        if ($excluirUuid) {
            $query->where('uuid', '!=', $excluirUuid);
        }

        $promedio = $query->value('promedio');

        return ($promedio !== null && (float) $promedio > 0) ? $this->redondear((float) $promedio) : null;
    }

    /**
     * Rendimiento estimado (km/gal) según el cilindraje, con la tabla de referencia
     * de camionetas. Se usa el punto medio de cada rango y se interpola entre rangos.
     */
    public function rendimientoPorCilindraje(?string $cilindraje): ?float
    {
        $cc = (float) preg_replace('/[^0-9.]/', '', (string) $cilindraje);
        if ($cc <= 0) {
            return null;
        }
        if ($cc < 50) {
            $cc *= 1000;
        }

        // Anclas (cc => km/gal): punto medio de cada rango de la tabla.
        $anclas = [
            [1000, 51.0],  // 1.0L - 1.6L: 45-57
            [1600, 51.0],
            [1800, 41.5],  // 1.8L - 2.0L: 38-45
            [2000, 41.5],
            [2400, 34.0],  // 2.4L - 2.5L: 30-38
            [2500, 34.0],
            [3000, 26.5],  // 3.0L - 3.6L: 23-30
            [3600, 26.5],
            [4000, 19.0],  // 4.0L - 5.7L+: 15-23
            [5700, 19.0],
        ];

        if ($cc <= $anclas[0][0]) {
            return $anclas[0][1];
        }

        $ultimo = $anclas[count($anclas) - 1];
        if ($cc >= $ultimo[0]) {
            return $ultimo[1];
        }

        for ($i = 1; $i < count($anclas); $i++) {
            [$cc2, $valor2] = $anclas[$i];
            [$cc1, $valor1] = $anclas[$i - 1];

            if ($cc <= $cc2) {
                if ($cc2 === $cc1) {
                    return $valor2;
                }
                $factor = ($cc - $cc1) / ($cc2 - $cc1);

                return round($valor1 + ($valor2 - $valor1) * $factor, 2);
            }
        }

        return $ultimo[1];
    }

    /**
     * Métricas de la fila derivadas del rendimiento aplicado (km/gal).
     *
     *   rendimiento (columna) = total del tanqueo / rendimiento aplicado
     *   cost_per_km           = total / recorrido; sin recorrido: precio / rendimiento
     *   estimated_range_km    = galones surtidos × rendimiento aplicado
     *
     * @param  array<string, mixed>  $calculados  salida de calcular()
     * @return array{rendimiento: ?float, cost_per_km: ?float, estimated_range_km: ?float}
     */
    public function metricasDeFila(array $calculados, float $totalTanqueo, float $precioGalon, ?float $rendimientoAplicado): array
    {
        $recorrido = $calculados['recorrido'];
        $galones = (float) ($calculados['gallons_pumped'] ?? 0);

        $rendimientoTotal = ($rendimientoAplicado !== null && $rendimientoAplicado > 0)
            ? $this->redondear($totalTanqueo / $rendimientoAplicado)
            : null;

        $costoKilometraje = ($recorrido !== null && $recorrido > 0)
            ? $this->redondear($totalTanqueo / $recorrido)
            : ($rendimientoAplicado !== null && $rendimientoAplicado > 0 ? $this->redondear($precioGalon / $rendimientoAplicado) : 0.0);

        $alcance = ($rendimientoAplicado !== null && $rendimientoAplicado > 0 && $galones > 0)
            ? $this->redondear($galones * $rendimientoAplicado)
            : null;

        return [
            'rendimiento' => $rendimientoTotal,
            'cost_per_km' => $costoKilometraje,
            'estimated_range_km' => $alcance,
        ];
    }

    /**
     * Redondeo financiero "half to even" (bancario) a 2 decimales.
     * Necesario para que 26.650,75 / 10.990 = 2,425 se registre como 2,42
     * (el redondeo por defecto de PHP daría 2,43 y rompería el contrato).
     */
    public function redondear(float $valor): float
    {
        return round($valor, 2, PHP_ROUND_HALF_EVEN);
    }

    // ─── Lecturas por lote (chunk de 100 vehículos) ────────────────────────

    /**
     * Último tanqueo por vehículo del lote. El orden ascendente + keyBy deja
     * la fila más reciente. La fecha de registro (created_at) viaja para que
     * la línea de base por preoperacional cercano pueda decidir por hora.
     *
     * @return Collection<string, object>
     */
    public function tanqueosDeLote(array $uuids): Collection
    {
        return DB::table('fuel_logs')
            ->whereIn('vehicle_uuid', $uuids)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['vehicle_uuid', 'uuid', 'date', 'final_odometer', 'estimated_range_km', 'average_performance', 'gallons_pumped', 'fuel_price_per_gallon', 'total_spent', 'cost_per_km', 'created_at'])
            ->keyBy('vehicle_uuid');
    }

    /**
     * Lecturas de preoperacional con odómetro, agrupadas por vehículo y
     * ordenadas por fecha: sirven para el kilometraje actual y para la línea
     * de base cuando el tanqueo no trae odómetro.
     *
     * @return Collection<string, Collection<int, object>>
     */
    public function inspeccionesDeLote(array $uuids): Collection
    {
        return DB::table('vehicle_inspections')
            ->whereIn('vehicle_uuid', $uuids)
            ->whereNotNull('mileage')
            ->orderBy('inspection_date')
            ->orderBy('id')
            ->get(['vehicle_uuid', 'inspection_date', 'mileage', 'created_at'])
            ->groupBy('vehicle_uuid');
    }

    /**
     * Kilometraje final de la planilla, el mayor por vehículo (orden por el
     * valor lanzado a entero + keyBy deja el máximo).
     *
     * @return Collection<string, object>
     */
    public function planillasDeLote(array $uuids): Collection
    {
        return DB::table('service_delivery_control_sheet as sdcs')
            ->join('service_internal_controls as sic', 'sic.service_delivery_control_sheet_uuid', '=', 'sdcs.uuid')
            ->whereIn('sic.vehicle_uuid', $uuids)
            ->whereNotNull('sdcs.ending_kilometer')
            ->orderByRaw('cast(sdcs.ending_kilometer as unsigned)')
            ->get(['sic.vehicle_uuid as vehicle_uuid', 'sdcs.service_date', 'sdcs.ending_kilometer'])
            ->keyBy('vehicle_uuid');
    }

    /**
     * Proyectos activos por vehículo (para el filtro del modal).
     *
     * @return Collection<string, Collection<int, object>>
     */
    public function proyectosDeLote(array $uuids): Collection
    {
        return DB::table('project_driver_vehicles as pdv')
            ->join('projects as p', 'p.uuid', '=', 'pdv.project_uuid')
            ->whereIn('pdv.vehicle_uuid', $uuids)
            ->where('pdv.is_active', 1)
            ->get(['pdv.vehicle_uuid', 'p.uuid as project_uuid', 'p.project_name'])
            ->groupBy('vehicle_uuid');
    }
}
