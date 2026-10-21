<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\FuelLog;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Control de Combustible, Kilometraje y Saldos.
 *
 * Fórmulas (todas redondeadas a 2 decimales):
 *   recorrido          = final_odometer - initial_odometer
 *   gallons_pumped     = total_spent / fuel_price_per_gallon     (0 si no hay tanqueo)
 *   gallons_consumed   = litros_motor × factor × horas / litros_por_galon
 *   final_balance      = initial_balance + gallons_pumped - gallons_consumed
 *   km_per_gallon      = recorrido / gallons_pumped              (null si no hay tanqueo)
 *   average_performance= km/gal propio -> promedio histórico     (null si no hay dato)
 *   estimated_range_km = gallons_pumped × average_performance    (null si no hay rendimiento)
 *   cost_per_km        = total_spent / recorrido                 (0 si no hay recorrido)
 *
 * initial_balance se hereda del final_balance del registro cronológicamente anterior
 * del mismo vehículo. Editar o eliminar un registro dispara una recalculación en
 * cascada de los registros posteriores de ese vehículo.
 */
class FuelLogService extends BaseService
{
    protected array $searchableFields = ['date'];

    protected function getModelInstance(): Model
    {
        return new FuelLog;
    }

    public function query(): Builder
    {
        return parent::query()->with(['vehicle', 'driver']);
    }

    // ─── Listados ──────────────────────────────────────────────────────────

    public function getPaginated(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', fn ($q) => $q->where('third_party_uuid', $thirdPartyUuid));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('date', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn ($qv) => $qv
                        ->where('vehicle_license_plate', 'like', "%{$search}%")
                        ->orWhere('internal_number', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($qd) => $qd
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function getByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    // ─── Contexto (precarga del formulario) ────────────────────────────────

    /**
     * Devuelve el kilometraje inicial, saldo inicial y el consumo estimado por
     * ralentí (según cilindraje/combustible) para el vehículo y fecha indicados.
     *
     * @return array<string, mixed>
     */
    public function getContext(string $vehicleUuid, ?string $date = null): array
    {
        $query = $this->query()->where('vehicle_uuid', $vehicleUuid);

        if ($date) {
            $query->where('date', '<', $date);
        }

        $anterior = $query->orderByDesc('date')->orderByDesc('id')->first();

        $vehiculo = $this->resolverConsumoVehiculo($vehicleUuid);
        $rendimiento = $this->resolverRendimientoDetallado(null, $vehicleUuid);

        return [
            'initial_odometer' => $anterior?->final_odometer,
            'initial_balance' => (float) ($anterior?->final_balance ?? 0),
            'previous_date' => $anterior?->date?->format('Y-m-d'),
            'engine_displacement' => $vehiculo['engine_displacement'],
            'fuel_type' => $vehiculo['fuel_type'],
            'displacement_liters' => $vehiculo['displacement_liters'],
            'idle_factor' => $vehiculo['idle_factor'],
            'idle_hours' => $vehiculo['idle_hours'],
            'estimated_gallons_consumed' => $this->consumoEstimadoGalones($vehiculo),
            'average_performance' => $rendimiento['valor'],
            'performance_source' => $rendimiento['origen'],
        ];
    }

    /**
     * Datos del vehículo necesarios para estimar el consumo por ralentí.
     *
     * @return array{engine_displacement: string|null, fuel_type: string, displacement_liters: float, idle_factor: float, idle_hours: float}
     */
    public function resolverConsumoVehiculo(?string $vehicleUuid): array
    {
        $cilindraje = null;
        $fuelType = 'OTRO';

        if ($vehicleUuid) {
            $vehiculo = \App\Models\Vehicle::withoutGlobalScopes()
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

    /**
     * Cilindraje del motor consultado desde el vehículo (vehicles.engine_displacement).
     * Se guarda como snapshot en el registro de combustible.
     */
    public function resolverCilindraje(?string $vehicleUuid): ?string
    {
        return $this->resolverConsumoVehiculo($vehicleUuid)['engine_displacement'];
    }

    // ─── Cálculo ───────────────────────────────────────────────────────────

    /**
     * Calcula los campos derivados de un registro.
     *
     * - Kilometraje opcional: si no llegan los odómetros, recorrido/km-gal/costo-km
     *   quedan vacíos (null/0) y no se rompe el cálculo.
     * - gallons_consumed es el consumo ESTIMADO por ralentí según cilindraje.
     *
     * @param  array  $datos
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
     * Redondeo financiero "half to even" (bancario) a 2 decimales.
     * Necesario para que 26.650,75 / 10.990 = 2,425 se registre como 2,42
     * (el redondeo por defecto de PHP daría 2,43 y rompería el contrato).
     */
    private function redondear(float $valor): float
    {
        return round($valor, 2, PHP_ROUND_HALF_EVEN);
    }

    /**
     * Resuelve el rendimiento promedio (km/gal) a aplicar y su origen.
     * Prioridad: valor provisto -> km/gal de la propia fila -> promedio histórico
     * del vehículo. Si no hay ningún dato calculable, no hay rendimiento.
     *
     * @return array{valor: ?float, origen: string}
     */
    public function resolverRendimientoDetallado(
        ?float $provisto,
        ?string $vehicleUuid,
        ?string $excluirUuid = null,
        ?float $propio = null
    ): array {
        if ($provisto !== null && $provisto > 0) {
            return ['valor' => $this->redondear($provisto), 'origen' => 'manual'];
        }

        if ($propio !== null && $propio > 0) {
            return ['valor' => $this->redondear($propio), 'origen' => 'registro'];
        }

        if ($vehicleUuid) {
            $query = $this->query()
                ->where('vehicle_uuid', $vehicleUuid)
                ->selectRaw("AVG(CASE WHEN km_per_gallon > 0 THEN km_per_gallon WHEN performance_source = 'manual' THEN average_performance END) as promedio");

            if ($excluirUuid) {
                $query->where('uuid', '!=', $excluirUuid);
            }

            $promedio = $query->value('promedio');
            if ($promedio !== null && $promedio > 0) {
                return ['valor' => $this->redondear((float) $promedio), 'origen' => 'historico'];
            }

            $cilindraje = \App\Models\Vehicle::withoutGlobalScopes()
                ->where('uuid', $vehicleUuid)
                ->value('engine_displacement');
            $tabla = $this->rendimientoPorCilindraje($cilindraje);
            if ($tabla !== null) {
                return ['valor' => $tabla, 'origen' => 'cilindraje'];
            }
        }

        return ['valor' => null, 'origen' => 'sin_dato'];
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
     * Resuelve el rendimiento promedio (km/gal) a aplicar (solo valor).
     */
    public function resolverRendimiento(?float $provisto, ?string $vehicleUuid, ?string $excluirUuid = null): ?float
    {
        return $this->resolverRendimientoDetallado($provisto, $vehicleUuid, $excluirUuid)['valor'];
    }

    // ─── CRUD ──────────────────────────────────────────────────────────────

    public function createFuelLog(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $companyUuid = $this->resolverEmpresa($data['company_uuid'] ?? null);
            $vehicleUuid = $data['vehicle_uuid'];

            // Datos del vehículo para el consumo estimado por ralentí.
            $vehiculo = $this->resolverConsumoVehiculo($vehicleUuid);
            $idleHours = max(0.0, (float) ($data['idle_hours'] ?? 0));

            // Continuidad: heredar saldo del último registro anterior.
            $contexto = $this->getContext($vehicleUuid, $data['date']);

            $calculados = $this->calcular([
                'initial_odometer' => $data['initial_odometer'] ?? null,
                'final_odometer' => $data['final_odometer'] ?? null,
                'total_spent' => $data['total_spent'] ?? 0,
                'fuel_price_per_gallon' => $data['fuel_price_per_gallon'] ?? 0,
                'displacement_liters' => $vehiculo['displacement_liters'],
                'idle_factor' => $vehiculo['idle_factor'],
                'idle_hours' => $idleHours,
                'initial_balance' => $contexto['initial_balance'],
            ]);

            $manual = $data['average_performance'] ?? null;
            $manual = ($manual !== null && (float) $manual > 0) ? $this->redondear((float) $manual) : null;

            $registro = FuelLog::create(array_merge($calculados, [
                'company_uuid' => $companyUuid,
                'vehicle_uuid' => $vehicleUuid,
                'driver_uuid' => $data['driver_uuid'] ?? null,
                'engine_displacement' => $vehiculo['engine_displacement'],
                'idle_hours' => $idleHours,
                'idle_factor' => $vehiculo['idle_factor'],
                'displacement_liters' => $vehiculo['displacement_liters'],
                'date' => $data['date'],
                'initial_odometer' => $data['initial_odometer'] ?? null,
                'final_odometer' => $data['final_odometer'] ?? null,
                'total_spent' => $data['total_spent'] ?? 0,
                'fuel_price_per_gallon' => $data['fuel_price_per_gallon'] ?? 0,
                'initial_balance' => $contexto['initial_balance'],
                'average_performance' => $manual,
                'performance_source' => $manual !== null ? 'manual' : null,
            ]));

            // Recalcula la cadena completa (por si se insertó en una fecha intermedia).
            $this->recalcularFlujo($vehicleUuid);

            return $registro->fresh()->load(['vehicle', 'driver']);
        });
    }

    public function updateFuelLog(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $registro = $this->findByUuid($uuid);
            if (! $registro) {
                throw new \RuntimeException('El registro de combustible no existe.');
            }

            $vehicleUuid = $data['vehicle_uuid'] ?? $registro->vehicle_uuid;
            $vehiculo = $this->resolverConsumoVehiculo($vehicleUuid);
            $idleHours = array_key_exists('idle_hours', $data)
                ? max(0.0, (float) $data['idle_hours'])
                : (float) ($registro->idle_hours ?? 0);

            $inicial = array_key_exists('initial_odometer', $data) ? $data['initial_odometer'] : $registro->initial_odometer;
            $final = array_key_exists('final_odometer', $data) ? $data['final_odometer'] : $registro->final_odometer;

            $calculados = $this->calcular([
                'initial_odometer' => $inicial,
                'final_odometer' => $final,
                'total_spent' => $data['total_spent'] ?? $registro->total_spent,
                'fuel_price_per_gallon' => $data['fuel_price_per_gallon'] ?? $registro->fuel_price_per_gallon,
                'displacement_liters' => $vehiculo['displacement_liters'],
                'idle_factor' => $vehiculo['idle_factor'],
                'idle_hours' => $idleHours,
                'initial_balance' => $registro->initial_balance, // se reajusta en la cascada
            ]);

            $cambios = array_merge($calculados, [
                'company_uuid' => $data['company_uuid'] ?? $registro->company_uuid,
                'vehicle_uuid' => $vehicleUuid,
                'driver_uuid' => array_key_exists('driver_uuid', $data) ? $data['driver_uuid'] : $registro->driver_uuid,
                'engine_displacement' => $vehiculo['engine_displacement'],
                'idle_hours' => $idleHours,
                'idle_factor' => $vehiculo['idle_factor'],
                'displacement_liters' => $vehiculo['displacement_liters'],
                'date' => $data['date'] ?? $registro->date,
                'initial_odometer' => $inicial,
                'final_odometer' => $final,
                'total_spent' => $data['total_spent'] ?? $registro->total_spent,
                'fuel_price_per_gallon' => $data['fuel_price_per_gallon'] ?? $registro->fuel_price_per_gallon,
            ]);

            // Rendimiento manual: si viene en la petición se respeta y se marca como
            // manual; si viene vacío se limpia para que la cascada lo recalcule.
            if (array_key_exists('average_performance', $data)) {
                $valor = $data['average_performance'];
                $manual = ($valor !== null && (float) $valor > 0) ? $this->redondear((float) $valor) : null;
                $cambios['average_performance'] = $manual;
                $cambios['performance_source'] = $manual !== null ? 'manual' : null;
            }

            $registro->update($cambios);

            $this->recalcularFlujo($vehicleUuid);

            return $registro->fresh()->load(['vehicle', 'driver']);
        });
    }

    public function deleteFuelLog(string $uuid): void
    {
        $this->transaction(function () use ($uuid) {
            $registro = $this->findByUuid($uuid);
            if (! $registro) {
                throw new \RuntimeException('El registro de combustible no existe.');
            }

            $vehicleUuid = $registro->vehicle_uuid;
            $registro->delete();

            // Al borrar un registro antiguo, la cadena posterior se recalcula.
            $this->recalcularFlujo($vehicleUuid);
        });
    }

    /**
     * Recalcula en orden cronológico los saldos y métricas de todos los registros
     * de un vehículo. Cada initial_balance se hereda del final_balance anterior.
     */
    public function recalcularFlujo(string $vehicleUuid): void
    {
        $registros = FuelLog::withoutGlobalScopes()
            ->where('vehicle_uuid', $vehicleUuid)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $saldoCorriente = 0.0;
        $vehiculo = $this->resolverConsumoVehiculo($vehicleUuid);

        // Promedio histórico del vehículo (una sola consulta para todo el flujo).
        // Incluye el km/gal medido y, cuando no hay medición, el rendimiento manual.
        $promedioHistorico = FuelLog::withoutGlobalScopes()
            ->where('vehicle_uuid', $vehicleUuid)
            ->selectRaw("AVG(CASE WHEN km_per_gallon > 0 THEN km_per_gallon WHEN performance_source = 'manual' THEN average_performance END) as promedio")
            ->value('promedio');
        $promedioHistorico = ($promedioHistorico !== null && $promedioHistorico > 0)
            ? $this->redondear((float) $promedioHistorico)
            : null;
        $rendimientoTabla = $this->rendimientoPorCilindraje($vehiculo['engine_displacement']);

        foreach ($registros as $registro) {
            $displacement = (float) ($registro->displacement_liters ?? 0);
            $factor = (float) ($registro->idle_factor ?? 0);
            $horas = (float) ($registro->idle_hours ?? 0);

            if ($displacement <= 0) {
                $displacement = $vehiculo['displacement_liters'];
            }
            if ($factor <= 0) {
                $factor = $vehiculo['idle_factor'];
            }

            $calculados = $this->calcular([
                'initial_odometer' => $registro->initial_odometer,
                'final_odometer' => $registro->final_odometer,
                'total_spent' => $registro->total_spent,
                'fuel_price_per_gallon' => $registro->fuel_price_per_gallon,
                'displacement_liters' => $displacement,
                'idle_factor' => $factor,
                'idle_hours' => $horas,
                'initial_balance' => $saldoCorriente,
            ]);

            // Rendimiento aplicado: manual (si el usuario lo fijó), km/gal propio de la
            // fila (si hay odómetros) o promedio histórico del vehículo.
            $propio = ($calculados['km_per_gallon'] !== null && (float) $calculados['km_per_gallon'] > 0)
                ? $this->redondear((float) $calculados['km_per_gallon'])
                : null;
            $manual = $registro->performance_source === 'manual' && (float) $registro->average_performance > 0;

            if ($manual) {
                $rendimiento = $this->redondear((float) $registro->average_performance);
                $origen = 'manual';
            } elseif ($propio !== null) {
                $rendimiento = $propio;
                $origen = 'registro';
            } elseif ($promedioHistorico !== null) {
                $rendimiento = $promedioHistorico;
                $origen = 'historico';
            } elseif ($rendimientoTabla !== null) {
                $rendimiento = $rendimientoTabla;
                $origen = 'cilindraje';
            } else {
                // Sin odómetros, histórico ni cilindraje: no hay rendimiento calculable.
                $rendimiento = null;
                $origen = 'sin_dato';
            }

            // Rendimiento del registro: Total Tanqueo ÷ Rendimiento (km/gal).
            $totalTanqueo = (float) $registro->total_spent;
            $precioGalon = (float) $registro->fuel_price_per_gallon;
            $recorrido = $calculados['recorrido'];
            $rendimientoTotal = ($rendimiento !== null && $rendimiento > 0)
                ? $this->redondear($totalTanqueo / $rendimiento)
                : null;
            $costoKilometraje = ($recorrido !== null && $recorrido > 0)
                ? $this->redondear($totalTanqueo / $recorrido)
                : ($rendimiento !== null && $rendimiento > 0 ? $this->redondear($precioGalon / $rendimiento) : 0.0);

            $registro->forceFill(array_merge($calculados, [
                'initial_balance' => $saldoCorriente,
                'displacement_liters' => $displacement,
                'idle_factor' => $factor,
                'idle_hours' => $horas,
                'average_performance' => $rendimiento,
                'performance_source' => $origen,
                'rendimiento' => $rendimientoTotal,
                'cost_per_km' => $costoKilometraje,
                'estimated_range_km' => ($rendimiento !== null && (float) $calculados['gallons_pumped'] > 0)
                    ? $this->redondear((float) $calculados['gallons_pumped'] * $rendimiento)
                    : null,
            ]))->save();

            $saldoCorriente = (float) $calculados['final_balance'];
        }

        Logger::info('FuelLogService: flujo de combustible recalculado', [
            'vehicle_uuid' => $vehicleUuid,
            'registros' => $registros->count(),
        ]);
    }

    /**
     * Resuelve la empresa del registro: la enviada, la del contexto de la petición
     * o la primera del usuario autenticado.
     */
    private function resolverEmpresa(?string $companyUuid): ?string
    {
        if ($companyUuid) {
            return $companyUuid;
        }

        $contexto = request()->attributes->get('current_company_uuid');
        if ($contexto) {
            return $contexto;
        }

        return Auth::user()?->companies()->first()?->uuid;
    }
}
