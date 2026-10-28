<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\FuelLog;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use App\Services\BaseService;
use App\Services\Fleet\Combustible\EstadoCombustible;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Control de Combustible, Kilometraje y Saldos.
 *
 * Conserva la escritura: CRUD con salvaguardas de pertenencia, herencia del
 * saldo (`initial_balance` del registro anterior) y recalculación en cascada
 * de los registros posteriores al editar o eliminar.
 *
 * Todas las fórmulas (recorrido, galones, saldos, km/gal, cadena de origen del
 * rendimiento, kilometraje actual y línea de base) viven en el módulo
 * EstadoCombustible (issue #8: un solo lugar para las reglas del dominio);
 * este servicio las consume y mantiene el orden de las consultas (scopes de
 * empresa y sede).
 *
 *   recorrido          = final_odometer - initial_odometer
 *   gallons_pumped     = total_spent / fuel_price_per_gallon     (0 si no hay tanqueo)
 *   gallons_consumed   = litros_motor × factor × horas / litros_por_galon
 *   final_balance      = initial_balance + gallons_pumped - gallons_consumed
 *   km_per_gallon      = recorrido / gallons_pumped              (null si no hay tanqueo)
 *   average_performance= km/gal propio -> promedio histórico     (null si no hay dato)
 */
class FuelLogService extends BaseService
{
    protected array $searchableFields = ['date'];

    public function __construct(private readonly EstadoCombustible $estadoCombustible)
    {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new FuelLog;
    }

    public function query(): Builder
    {
        $query = parent::query()->with(['vehicle', 'driver']);

        // El registro no tiene sede propia: se hereda la del vehículo. Con el
        // rol SUCURSAL, `whereHas('vehicle')` aplica el scope de sede del modelo
        // Vehicle y deja fuera los registros de otras sedes.
        if (request()->attributes->get('branch_restricted', false)) {
            $query->whereHas('vehicle');
        }

        return $query;
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
        // Falla cerrado si el vehículo es de otra empresa o sede: sin esto el
        // endpoint /context devolvía cilindraje y rendimiento de un vehículo
        // ajeno (el módulo consulta con scopes).
        if (! Vehicle::query()->where('uuid', $vehicleUuid)->exists()) {
            throw ValidationException::withMessages([
                'vehicle_uuid' => 'El vehículo no pertenece a la empresa o sede activa.',
            ]);
        }

        $query = $this->query()->where('vehicle_uuid', $vehicleUuid);

        if ($date) {
            $query->where('date', '<', $date);
        }

        $anterior = $query->orderByDesc('date')->orderByDesc('id')->first();

        $vehiculo = $this->estadoCombustible->datosDeConsumo($vehicleUuid);
        $rendimiento = $this->rendimientoDetallado(null, $vehicleUuid);

        return [
            'initial_odometer' => $anterior?->final_odometer,
            'initial_balance' => (float) ($anterior?->final_balance ?? 0),
            'previous_date' => $anterior?->date?->format('Y-m-d'),
            'engine_displacement' => $vehiculo['engine_displacement'],
            'fuel_type' => $vehiculo['fuel_type'],
            'displacement_liters' => $vehiculo['displacement_liters'],
            'idle_factor' => $vehiculo['idle_factor'],
            'idle_hours' => $vehiculo['idle_hours'],
            'estimated_gallons_consumed' => $this->estadoCombustible->consumoEstimadoGalones($vehiculo),
            'average_performance' => $rendimiento['valor'],
            'performance_source' => $rendimiento['origen'],
        ];
    }

    /**
     * Resuelve el rendimiento (km/gal) a aplicar y su origen para el
     * precargado del formulario. Los datos del vehículo y del histórico se
     * consultan solo si la cadena de origen llega hasta ellos.
     */
    private function rendimientoDetallado(?float $provisto, ?string $vehicleUuid): array
    {
        return $this->estadoCombustible->rendimientoDeCadena(
            $provisto,
            null,
            fn () => $vehicleUuid === null
                ? null
                : $this->estadoCombustible->promedioHistorico(
                    (clone $this->query())->where('vehicle_uuid', $vehicleUuid)
                ),
            fn () => $vehicleUuid === null
                ? null
                : $this->estadoCombustible->rendimientoPorCilindraje(
                    Vehicle::query()->where('uuid', $vehicleUuid)->value('engine_displacement')
                )
        );
    }

    // ─── CRUD ──────────────────────────────────────────────────────────────

    public function createFuelLog(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $companyUuid = $this->resolverEmpresa($data['company_uuid'] ?? null);
            $vehicleUuid = $data['vehicle_uuid'];

            // Defensa en profundidad: el request ya acota el vehículo y el
            // conductor a la empresa/sede, pero el servicio también puede
            // invocarse desde otros flujos.
            if (! Vehicle::query()->where('uuid', $vehicleUuid)->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_uuid' => 'El vehículo no pertenece a la empresa o sede activa.',
                ]);
            }
            if (! empty($data['driver_uuid']) && ! ThirdParty::query()->where('uuid', $data['driver_uuid'])->exists()) {
                throw ValidationException::withMessages([
                    'driver_uuid' => 'El conductor no pertenece a la empresa o sede activa.',
                ]);
            }

            // Datos del vehículo para el consumo estimado por ralentí.
            $vehiculo = $this->estadoCombustible->datosDeConsumo($vehicleUuid);
            $idleHours = max(0.0, (float) ($data['idle_hours'] ?? 0));

            // Continuidad: heredar saldo del último registro anterior.
            $contexto = $this->getContext($vehicleUuid, $data['date']);

            $calculados = $this->estadoCombustible->calcular([
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
            $manual = ($manual !== null && (float) $manual > 0) ? $this->estadoCombustible->redondear((float) $manual) : null;

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
            $this->recalcularFlujo($vehicleUuid, $companyUuid);

            // Si había una solicitud de tanqueo aprobada y vigente, queda usada.
            app(FuelRequestService::class)->marcarUsada($vehicleUuid, (string) $registro->uuid);

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

            $vehicleAnterior = $registro->vehicle_uuid;
            $vehicleUuid = $data['vehicle_uuid'] ?? $registro->vehicle_uuid;

            // El registro no cambia de empresa; el vehículo/conductor nuevos
            // deben ser visibles en el contexto (empresa y sede) del usuario.
            if (! Vehicle::query()->where('uuid', $vehicleUuid)->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_uuid' => 'El vehículo no pertenece a la empresa o sede activa.',
                ]);
            }
            if (! empty($data['driver_uuid']) && ! ThirdParty::query()->where('uuid', $data['driver_uuid'])->exists()) {
                throw ValidationException::withMessages([
                    'driver_uuid' => 'El conductor no pertenece a la empresa o sede activa.',
                ]);
            }

            $vehiculo = $this->estadoCombustible->datosDeConsumo($vehicleUuid);
            $idleHours = array_key_exists('idle_hours', $data)
                ? max(0.0, (float) $data['idle_hours'])
                : (float) ($registro->idle_hours ?? 0);

            $inicial = array_key_exists('initial_odometer', $data) ? $data['initial_odometer'] : $registro->initial_odometer;
            $final = array_key_exists('final_odometer', $data) ? $data['final_odometer'] : $registro->final_odometer;

            $calculados = $this->estadoCombustible->calcular([
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
                'company_uuid' => $registro->company_uuid,
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
                $manual = ($valor !== null && (float) $valor > 0) ? $this->estadoCombustible->redondear((float) $valor) : null;
                $cambios['average_performance'] = $manual;
                $cambios['performance_source'] = $manual !== null ? 'manual' : null;
            }

            $registro->update($cambios);

            // Si el registro cambió de vehículo, la cadena del vehículo anterior
            // también queda descuadrada y hay que recalcularla.
            if ($vehicleAnterior !== $vehicleUuid) {
                $this->recalcularFlujo($vehicleAnterior, $registro->company_uuid);
            }
            $this->recalcularFlujo($vehicleUuid, $registro->company_uuid);

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
            $companyUuid = $registro->company_uuid;
            $registro->delete();

            // Al borrar un registro antiguo, la cadena posterior se recalcula.
            $this->recalcularFlujo($vehicleUuid, $companyUuid);
        });
    }

    /**
     * Recalcula en orden cronológico los saldos y métricas de todos los registros
     * de un vehículo. Cada initial_balance se hereda del final_balance anterior.
     */
    public function recalcularFlujo(string $vehicleUuid, ?string $companyUuid = null): void
    {
        // Con el scope de empresa (y el de sede del listado) no se tocan
        // registros de otra empresa; `companyUuid` cierra el caso de llamadas
        // internas sin contexto de petición.
        $base = $this->query()->where('vehicle_uuid', $vehicleUuid);
        if ($companyUuid) {
            $base->where('company_uuid', $companyUuid);
        }

        $registros = (clone $base)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $saldoCorriente = 0.0;
        $vehiculo = $this->estadoCombustible->datosDeConsumo($vehicleUuid);

        // Promedio histórico del vehículo (una sola consulta para todo el flujo)
        // y rendimiento por cilindraje, ya redondeados por el módulo.
        $promedioHistorico = $this->estadoCombustible->promedioHistorico(clone $base);
        $rendimientoTabla = $this->estadoCombustible->rendimientoPorCilindraje($vehiculo['engine_displacement']);

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

            $calculados = $this->estadoCombustible->calcular([
                'initial_odometer' => $registro->initial_odometer,
                'final_odometer' => $registro->final_odometer,
                'total_spent' => $registro->total_spent,
                'fuel_price_per_gallon' => $registro->fuel_price_per_gallon,
                'displacement_liters' => $displacement,
                'idle_factor' => $factor,
                'idle_hours' => $horas,
                'initial_balance' => $saldoCorriente,
            ]);

            // Rendimiento aplicado: manual (si el usuario lo fijó), km/gal propio
            // de la fila (si hay odómetros), promedio histórico del vehículo o
            // tabla por cilindraje; sin nada de eso, sin dato.
            $manual = ($registro->performance_source === 'manual' && (float) $registro->average_performance > 0)
                ? (float) $registro->average_performance
                : null;
            $propio = ($calculados['km_per_gallon'] !== null && (float) $calculados['km_per_gallon'] > 0)
                ? (float) $calculados['km_per_gallon']
                : null;

            $rendimiento = $this->estadoCombustible->rendimientoDeCadena(
                $manual,
                $propio,
                fn () => $promedioHistorico,
                fn () => $rendimientoTabla
            );

            $metricas = $this->estadoCombustible->metricasDeFila(
                $calculados,
                (float) $registro->total_spent,
                (float) $registro->fuel_price_per_gallon,
                $rendimiento['valor']
            );

            $registro->forceFill(array_merge($calculados, [
                'initial_balance' => $saldoCorriente,
                'displacement_liters' => $displacement,
                'idle_factor' => $factor,
                'idle_hours' => $horas,
                'average_performance' => $rendimiento['valor'],
                'performance_source' => $rendimiento['origen'],
                'rendimiento' => $metricas['rendimiento'],
                'cost_per_km' => $metricas['cost_per_km'],
                'estimated_range_km' => $metricas['estimated_range_km'],
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
        // El contexto manda: `SetCompanyContext` ya validó la pertenencia del
        // header o del cuerpo. El valor recibido solo se usa en flujos internos
        // sin petición (consola, importaciones).
        $contexto = request()->attributes->get('current_company_uuid');
        if ($contexto) {
            return $contexto;
        }

        if ($companyUuid) {
            return $companyUuid;
        }

        return Auth::user()?->companies()->first()?->uuid;
    }
}
