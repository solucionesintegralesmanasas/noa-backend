<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Exceptions\GeneralException;
use App\Models\CapacityMovement;
use App\Models\OperationCard;
use App\Models\Vehicle;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de TarjetaOperacion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class OperationCardService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [
        'operating_card_number',
        'service_type',
        'transport_mode',
        'area_of_coverage',
        'affiliated_company',
    ];

    protected function getModelInstance(): Model
    {
        return new OperationCard;
    }

    /**
     * Método getAllOperationCardsWithPagination.
     */
    public function getAllOperationCardsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null,
        ?string $vehicleUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with('vehicle');

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }

                $q->orWhereHas('vehicle', function ($qVeh) use ($search) {
                    $qVeh->where('vehicle_license_plate', 'like', "%{$search}%")
                        ->orWhere('internal_number', 'like', "%{$search}%");
                });
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Método getAllOperationCards.
     */
    public function getAllOperationCards(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with('vehicle');

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->whereHas('vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        return $query->get();
    }

    /**
     * Método getOperationCardByUuid.
     */
    public function getOperationCardByUuid(string $uuid): ?Model
    {
        $record = $this->findByUuid($uuid);
        if ($record) {
            $record->load('vehicle');
        }

        return $record;
    }

    /**
     * Método createOperationCard.
     */
    public function createOperationCard(array $data): Model
    {
        $this->asegurarQueElVehiculoRequiereTarjeta($data['vehicle_uuid'] ?? null);

        return $this->transaction(function () use ($data) {
            $tipo = $this->resolverTipoTarjeta($data);
            $movimientoCupo = $this->verificarCupoParaTarjeta($tipo, $data['vehicle_uuid'] ?? null, $data['company_uuid'] ?? null);

            $internalNumber = $data['internal_number'] ?? null;
            $vehicle = Vehicle::where('uuid', $data['vehicle_uuid'])->first();
            if ($vehicle) {
                if (empty($internalNumber)) {
                    if (empty($vehicle->internal_number)) {
                        $config = \App\Models\SystemConfiguration::query()->where('company_uuid', $data['company_uuid'])->first();
                        if ($config && $config->fuec_enable_auto_internal_number && $config->vehicle_internal_number_counter !== null) {
                            $hasAgreements = \Illuminate\Support\Facades\DB::table('business_collaboration_agreements')
                                ->where('vehicle_uuid', $vehicle->uuid)
                                ->exists();

                            if (!$hasAgreements) {
                                $internalNumber = (string) $config->vehicle_internal_number_counter;
                                $config->increment('vehicle_internal_number_counter', 1, []);
                                $vehicle->update(['internal_number' => $internalNumber]);
                            }
                        }
                    }
                } else {
                    $vehicle->update(['internal_number' => $internalNumber]);
                }
            }

            $card = OperationCard::create([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'],
                'affiliated_company' => $data['affiliated_company'],
                'area_of_coverage' => $data['area_of_coverage'] ?? 'NACIONAL',
                'service_type' => $data['service_type'],
                'transport_mode' => $data['transport_mode'],
                'issue_date' => $data['issue_date'],
                'expiration_date' => $data['expiration_date'],
                'operating_card_number' => $data['operating_card_number'],
                'operation_card_type' => $tipo,
                'runt_status' => 'ACTIVA',
                'procedure_uuid' => $data['procedure_uuid'] ?? null,
                'status' => $data['status'] ?? 1,
            ]);

            if ($movimientoCupo) {
                app(CapacityMovementService::class)->vincularTarjeta($movimientoCupo, $card->uuid);
            }

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($card->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras crear tarjeta de operación: '.$e->getMessage());
            }

            return $card;
        });
    }

    /**
     * Método updateOperationCard.
     */
    public function updateOperationCard(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $vehicleUuid = $data['vehicle_uuid'] ?? $record->vehicle_uuid;
            // Solo si cambia de vehículo: la tarjeta histórica de un vehículo ya particular se puede editar.
            if ($vehicleUuid !== $record->vehicle_uuid) {
                $this->asegurarQueElVehiculoRequiereTarjeta($vehicleUuid);
            }
            $vehicle = Vehicle::where('uuid', $vehicleUuid)->first();
            $internalNumber = $data['internal_number'] ?? null;
            if ($vehicle) {
                if (empty($internalNumber)) {
                    if (empty($vehicle->internal_number)) {
                        $config = \App\Models\SystemConfiguration::query()->where('company_uuid', $record->company_uuid)->first();
                        if ($config && $config->fuec_enable_auto_internal_number && $config->vehicle_internal_number_counter !== null) {
                            $hasAgreements = \Illuminate\Support\Facades\DB::table('business_collaboration_agreements')
                                ->where('vehicle_uuid', $vehicle->uuid)
                                ->exists();

                            if (!$hasAgreements) {
                                $internalNumber = (string) $config->vehicle_internal_number_counter;
                                $config->increment('vehicle_internal_number_counter', 1, []);
                                $vehicle->update(['internal_number' => $internalNumber]);
                            }
                        }
                    }
                } else {
                    $vehicle->update(['internal_number' => $internalNumber]);
                }
            }

            $tipoNuevo = ! empty($data['operation_card_type']) ? strtoupper((string) $data['operation_card_type']) : null;
            if ($tipoNuevo !== null && $tipoNuevo !== $record->operation_card_type
                && OperationCard::tipoConsumeCupo($tipoNuevo)
                && $record->runt_status === 'ACTIVA'
                && ! $record->capacityMovements()->exists()) {
                $this->verificarCupoParaTarjeta($tipoNuevo, $vehicleUuid, $record->company_uuid);
            }

            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'affiliated_company' => $data['affiliated_company'] ?? $record->affiliated_company,
                'area_of_coverage' => $data['area_of_coverage'] ?? $record->area_of_coverage,
                'service_type' => $data['service_type'] ?? $record->service_type,
                'transport_mode' => $data['transport_mode'] ?? $record->transport_mode,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'expiration_date' => $data['expiration_date'] ?? $record->expiration_date,
                'operating_card_number' => $data['operating_card_number'] ?? $record->operating_card_number,
                'operation_card_type' => $data['operation_card_type'] ?? $record->operation_card_type,
                'runt_status' => $data['runt_status'] ?? $record->runt_status,
                'procedure_uuid' => $data['procedure_uuid'] ?? $record->procedure_uuid,
                'status' => $data['status'] ?? $record->status,
            ]);

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras actualizar tarjeta de operación: '.$e->getMessage());
            }

            return $record->fresh();
        });
    }

    /**
     * Método deleteOperationCard.
     */
    public function deleteOperationCard(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('OperationCardService@deleteOperationCard: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método toggleOperationCardStatus.
     */
    public function toggleOperationCardStatus(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'status' => ! $record->status,
            ]);

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras alternar estado de tarjeta de operación: '.$e->getMessage());
            }

            return $record->fresh();
        });
    }

    /**
     * Cancela (inhabilita) las tarjetas vigentes de un vehículo cuando sale de la
     * empresa: desvinculación (mutuo o unilateral) o cambio de empresa de salida.
     * Es el efecto directo de liberar el cupo.
     *
     * @return int Número de tarjetas canceladas.
     */
    public function cancelarPorSalida(?string $vehicleUuid, ?string $companyUuid = null, ?string $motivo = null): int
    {
        if (empty($vehicleUuid)) {
            return 0;
        }

        $query = OperationCard::withoutGlobalScopes()
            ->where('vehicle_uuid', $vehicleUuid)
            ->where('runt_status', '!=', 'CANCELADA');

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        $canceladas = $query->update([
            'status' => 0,
            'runt_status' => 'CANCELADA',
            'cancelled_at' => now(),
            'cancellation_reason' => $motivo,
            'updated_at' => now(),
        ]);

        if ($canceladas > 0) {
            Logger::info("Tarjetas de operación canceladas por salida del vehículo {$vehicleUuid}: {$canceladas}.", [
                'company_uuid' => $companyUuid,
                'motivo' => $motivo,
            ]);
        }

        return $canceladas;
    }

    /**
     * Tipo de tarjeta: el declarado en la petición o, si no viene, inferido por el
     * historial del vehículo (primera tarjeta = NUEVA; si ya tenía una vigente = RENOVACION).
     */
    private function resolverTipoTarjeta(array $data): string
    {
        if (! empty($data['operation_card_type'])) {
            return strtoupper((string) $data['operation_card_type']);
        }

        $yaTenia = OperationCard::withoutGlobalScopes()
            ->where('vehicle_uuid', $data['vehicle_uuid'] ?? null)
            ->where('runt_status', '!=', 'CANCELADA')
            ->exists();

        return $yaTenia ? 'RENOVACION' : 'NUEVA';
    }

    /**
     * Comprueba que un cupo esté descontado antes de emitir una tarjeta que lo
     * consume (NUEVA o CAMBIO_DE_EMPRESA). En modo estricto
     * (`capacity.enforce_on_new_operation_card`) rechaza; en modo auditoría deja
     * constancia en el log y permite continuar.
     *
     * @return CapacityMovement|null Movimiento de descuento pendiente de vincular, si existe.
     *
     * @throws GeneralException
     */
    private function verificarCupoParaTarjeta(string $tipo, ?string $vehicleUuid, ?string $companyUuid): ?CapacityMovement
    {
        if (! OperationCard::tipoConsumeCupo($tipo) || empty($vehicleUuid)) {
            return null;
        }

        $movimiento = app(CapacityMovementService::class)
            ->descuentoPendienteDeVehiculo($vehicleUuid, $companyUuid);

        if ($movimiento) {
            return $movimiento;
        }

        $mensaje = "La tarjeta {$tipo} requiere un descuento de cupo previo (capacidad transportadora) que no existe.";
        if ((bool) config('capacity.enforce_on_new_operation_card', false)) {
            throw GeneralException::unprocessable($mensaje);
        }

        Logger::warning('Cupo no descontado antes de emitir la tarjeta de operación (modo auditoría).', null, [
            'vehicle_uuid' => $vehicleUuid,
            'company_uuid' => $companyUuid,
            'operation_card_type' => $tipo,
        ]);

        return null;
    }

    /**
     * Un vehículo particular no tiene tarjeta de operación. Defensa en el servicio por si se
     * invoca fuera de un FormRequest.
     *
     * @throws GeneralException
     */
    private function asegurarQueElVehiculoRequiereTarjeta(?string $vehicleUuid): void
    {
        $vehiculo = $vehicleUuid ? Vehicle::withoutGlobalScopes()->where('uuid', $vehicleUuid)->first() : null;

        if ($vehiculo && ! $vehiculo->requiereTarjetaOperacion()) {
            throw GeneralException::unprocessable('Un vehículo particular no tiene tarjeta de operación.');
        }
    }
}
