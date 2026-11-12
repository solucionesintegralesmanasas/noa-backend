<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\CapacityMovement;
use App\Models\ConveyorCapacity;
use App\Models\Vehicle;
use App\Utils\Logger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Motor de cupos de capacidad transportadora.
 *
 * Única vía para mover `conveyor_capacity.current_capacity` y dejar rastro en el
 * libro mayor `capacity_movements`. Implementa la regla del Ministerio:
 *
 *  - Se DESCUENTA un cupo al aprobar la capacidad transportadora de un vehículo
 *    nuevo o un ingreso por cambio de empresa.
 *  - Se LIBERA un cupo al quedar en firme una desvinculación o una salida por
 *    cambio de empresa.
 *  - La RENOVACIÓN de la tarjeta no mueve el contador.
 *
 * Con `config('capacity.enforce')` en false el motor corre en modo auditoría:
 * registra el movimiento que correspondería (`applied = false`) sin mutar el
 * cupo ni lanzar error.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-10-15
 */
class CapacityMovementService
{
    /**
     * Registra y aplica un movimiento de cupo. Idempotente por
     * (procedure_uuid, movement_type): repetir el mismo paso no mueve el cupo dos veces.
     *
     * @param  array{
     *     company_uuid: string,
     *     movement_type: string,
     *     vehicle_uuid?: string|null,
     *     vehicle_type?: string|null,
     *     procedure_uuid?: string|null,
     *     operation_card_uuid?: string|null,
     *     quantity?: int,
     *     resolution_number?: string|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws \App\Exceptions\GeneralException cuando el movimiento es inválido y el motor está en modo estricto
     */
    public function registrar(array $data): CapacityMovement
    {
        $tipo = $data['movement_type'];
        if (CapacityMovement::signo($tipo) === 0) {
            throw new \InvalidArgumentException("El tipo de movimiento '{$tipo}' no mueve cupos.");
        }

        return DB::transaction(function () use ($data, $tipo) {
            $procedureUuid = $data['procedure_uuid'] ?? null;

            // Idempotencia: un mismo paso de trámite no mueve el cupo dos veces.
            if (! empty($procedureUuid)) {
                $existente = $this->movimientoExistente($procedureUuid, $tipo);
                if ($existente) {
                    return $existente;
                }
            }

            $capacidad = $this->resolverCapacidad($data['company_uuid'], $data['vehicle_type'] ?? null);
            $enforce = (bool) config('capacity.enforce', true);
            $cantidad = max(1, (int) ($data['quantity'] ?? 1));
            $signo = CapacityMovement::signo($tipo);

            if (! $capacidad) {
                if ($enforce) {
                    throw \App\Exceptions\GeneralException::unprocessable(
                        'La empresa no tiene capacidad transportadora configurada para el tipo de vehículo.'
                    );
                }

                return CapacityMovement::create([
                    'company_uuid' => $data['company_uuid'],
                    'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                    'vehicle_type' => $data['vehicle_type'] ?? null,
                    'procedure_uuid' => $procedureUuid,
                    'operation_card_uuid' => $data['operation_card_uuid'] ?? null,
                    'movement_type' => $tipo,
                    'quantity' => $cantidad,
                    'applied' => false,
                    'resolution_number' => $data['resolution_number'] ?? null,
                    'notes' => trim(($data['notes'] ?? '').' (sin capacidad configurada; modo auditoría)'),
                    'created_by' => Auth::id(),
                ]);
            }

            $bloqueada = ConveyorCapacity::withoutGlobalScopes()
                ->whereKey($capacidad->getKey())
                ->lockForUpdate()
                ->first();

            $antes = (int) $bloqueada->current_capacity;
            $despues = $antes + $signo * $cantidad;

            if ($enforce && $despues < 0) {
                throw \App\Exceptions\GeneralException::unprocessable(
                    'La empresa no tiene cupos disponibles de capacidad transportadora.'
                );
            }

            if ($enforce && $despues > (int) $bloqueada->authorized_capacity) {
                throw \App\Exceptions\GeneralException::unprocessable(
                    'La liberación supera la capacidad autorizada de la empresa.'
                );
            }

            $movimiento = CapacityMovement::create([
                'company_uuid' => $data['company_uuid'],
                'conveyor_capacity_uuid' => $bloqueada->uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'vehicle_type' => $data['vehicle_type'] ?? $bloqueada->vehicle_type,
                'procedure_uuid' => $procedureUuid,
                'operation_card_uuid' => $data['operation_card_uuid'] ?? null,
                'movement_type' => $tipo,
                'quantity' => $cantidad,
                'capacity_before' => $antes,
                'capacity_after' => $despues,
                'applied' => $enforce,
                'resolution_number' => $data['resolution_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if ($enforce) {
                $bloqueada->update(['current_capacity' => $despues]);
            }

            Logger::info('Cupo '.($signo < 0 ? 'descontado' : 'liberado')." ({$tipo}): {$antes} -> {$despues}.", [
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'procedure_uuid' => $procedureUuid,
            ]);

            return $movimiento;
        });
    }

    /**
     * Busca un movimiento de descuento de un vehículo que aún no se ha vinculado a
     * ninguna tarjeta. Es la evidencia de que el cupo ya se descontó antes de emitir
     * la tarjeta de operación (NUEVA o ingreso por cambio de empresa).
     */
    public function descuentoPendienteDeVehiculo(string $vehicleUuid, ?string $companyUuid = null): ?CapacityMovement
    {
        $query = CapacityMovement::withoutGlobalScopes()
            ->where('vehicle_uuid', $vehicleUuid)
            ->whereIn('movement_type', CapacityMovement::TIPOS_DESCUENTO)
            ->whereNull('operation_card_uuid')
            ->orderBy('id');

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        return $query->first();
    }

    /** Vincula un movimiento de descuento con la tarjeta de operación emitida. */
    public function vincularTarjeta(CapacityMovement $movimiento, string $operationCardUuid): void
    {
        $movimiento->update(['operation_card_uuid' => $operationCardUuid]);
    }

    /**
     * Consulta (sin mutar nada) el cupo disponible de la empresa para un tipo de
     * vehículo. Devuelve `null` cuando la empresa no tiene capacidad activa
     * configurada, para que el llamador decida si eso es error o auditoría.
     *
     * @return array{
     *     conveyor_capacity_uuid: string,
     *     vehicle_type: string|null,
     *     authorized_capacity: int,
     *     current_capacity: int,
     *     available: bool
     * }|null
     */
    public function cupoDisponible(string $companyUuid, ?string $vehicleType = null): ?array
    {
        $capacidad = $this->resolverCapacidad($companyUuid, $vehicleType);
        if (! $capacidad) {
            return null;
        }

        $actual = (int) $capacidad->current_capacity;

        return [
            'conveyor_capacity_uuid' => $capacidad->uuid,
            'vehicle_type' => $capacidad->vehicle_type,
            'authorized_capacity' => (int) $capacidad->authorized_capacity,
            'current_capacity' => $actual,
            'available' => $actual > 0,
        ];
    }

    /**
     * Historial paginado del libro mayor, con el saldo real de cada capacidad para
     * detectar desvíos frente a la conciliación.
     *
     * @param  array{movement_type?: string|null, vehicle_uuid?: string|null, desde?: string|null, hasta?: string|null, company_uuid?: string|null}  $filtros
     */
    public function listarMovimientos(array $filtros = [], int $perPage = 15, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = CapacityMovement::with([
            'vehicle:uuid,vehicle_license_plate,internal_number',
            'procedure:uuid,procedure_code,procedure_type,link_type',
            'conveyorCapacity:uuid,vehicle_type,authorized_capacity,current_capacity',
        ])->orderByDesc('id');

        if (! empty($filtros['company_uuid'])) {
            $query->where('company_uuid', $filtros['company_uuid']);
        }
        if (! empty($filtros['movement_type'])) {
            $query->where('movement_type', $filtros['movement_type']);
        }
        if (! empty($filtros['vehicle_uuid'])) {
            $query->where('vehicle_uuid', $filtros['vehicle_uuid']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Diferencia entre el saldo guardado y el neto del libro mayor, por capacidad
     * de la empresa. Un valor distinto de cero señala que hay que conciliar.
     *
     * Los queries van sin scope de empresa porque la conciliación se acota por el
     * `company_uuid` explícito (y por el de la resolución de habilitación).
     *
     * @return array<int, array<string, mixed>>
     */
    public function conciliacionDeEmpresa(string $companyUuid): array
    {
        $capacidades = ConveyorCapacity::withoutGlobalScopes()
            ->whereHas('enablingResolution', fn ($q) => $q->withoutGlobalScopes()->where('company_uuid', $companyUuid))
            ->orderBy('id')
            ->get(['uuid', 'vehicle_type', 'authorized_capacity', 'current_capacity']);

        $netos = CapacityMovement::withoutGlobalScopes()
            ->where('company_uuid', $companyUuid)
            ->get(['conveyor_capacity_uuid', 'movement_type', 'quantity'])
            ->groupBy('conveyor_capacity_uuid')
            ->map(fn ($movimientos) => $movimientos->sum(
                fn (CapacityMovement $m) => CapacityMovement::signo($m->movement_type) * (int) $m->quantity
            ));

        return $capacidades->map(function (ConveyorCapacity $capacidad) use ($netos) {
            // El libro mayor solo explica el movimiento; el saldo autorizado es el
            // punto de partida, así que el esperado es autorizado + neto.
            $neto = (int) ($netos[$capacidad->uuid] ?? 0);
            $esperado = (int) $capacidad->authorized_capacity + $neto;
            $actual = (int) $capacidad->current_capacity;

            return [
                'conveyor_capacity_uuid' => $capacidad->uuid,
                'vehicle_type' => $capacidad->vehicle_type,
                'authorized_capacity' => (int) $capacidad->authorized_capacity,
                'current_capacity' => $actual,
                'movimientos_netos' => $neto,
                'esperado' => $esperado,
                'diferencia' => $actual - $esperado,
            ];
        })->values()->all();
    }

    /** Movimiento ya registrado para el mismo paso y tipo (idempotencia). */
    private function movimientoExistente(string $procedureUuid, string $tipo): ?CapacityMovement
    {
        return CapacityMovement::withoutGlobalScopes()
            ->where('procedure_uuid', $procedureUuid)
            ->where('movement_type', $tipo)
            ->first();
    }

    /**
     * Resuelve la capacidad transportadora de la empresa para un tipo de vehículo.
     * Prioriza la coincidencia exacta de `vehicle_type`; si no hay, y la empresa
     * tiene una sola capacidad activa, la usa. Sin coincidencia ni capacidad única
     * devuelve null (el motor decide si eso es error o auditoría).
     */
    private function resolverCapacidad(string $companyUuid, ?string $vehicleType): ?ConveyorCapacity
    {
        $base = static fn () => ConveyorCapacity::withoutGlobalScopes()
            ->whereHas('enablingResolution', fn ($q) => $q->withoutGlobalScopes()->where('company_uuid', $companyUuid))
            ->where('status', true);

        if (! empty($vehicleType)) {
            $exacta = $base()->where('vehicle_type', $vehicleType)->orderBy('id')->first();
            if ($exacta) {
                return $exacta;
            }
        }

        // Sin coincidencia de tipo, y aunque haya varias, se toma la primera activa:
        // es preferible un cupo afectado y auditable a bloquear el trámite.
        return $base()->orderBy('id')->first();
    }
}
