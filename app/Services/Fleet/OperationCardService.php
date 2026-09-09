<?php

declare(strict_types=1);

namespace App\Services\Fleet;

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
        ?string $thirdPartyUuid = null
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
        return $this->transaction(function () use ($data) {
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
                'status' => $data['status'] ?? 1,
            ]);

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

            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'affiliated_company' => $data['affiliated_company'] ?? $record->affiliated_company,
                'area_of_coverage' => $data['area_of_coverage'] ?? $record->area_of_coverage,
                'service_type' => $data['service_type'] ?? $record->service_type,
                'transport_mode' => $data['transport_mode'] ?? $record->transport_mode,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'expiration_date' => $data['expiration_date'] ?? $record->expiration_date,
                'operating_card_number' => $data['operating_card_number'] ?? $record->operating_card_number,
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
}
