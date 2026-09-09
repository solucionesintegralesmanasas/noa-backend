<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\BusinessCollaborationAgreement;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de ConvenioColaboracionEmpresarial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class BusinessCollaborationAgreementService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = [
        'resolution_number',
        'agreement_internal_id',
        'contracting_entity_nit',
        'contracting_entity_name',
        'rep_name',
        'transport_modality',
    ];

    protected function getModelInstance(): Model
    {
        return new BusinessCollaborationAgreement;
    }

    /**
     * Método getAllBusinessCollaborationAgreementsWithPagination.
     */
    public function getAllBusinessCollaborationAgreementsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with(['vehicle']);

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
     * Método getAllBusinessCollaborationAgreements.
     */
    public function getAllBusinessCollaborationAgreements(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with(['vehicle']);

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
     * Método getBusinessCollaborationAgreementByUuid.
     */
    public function getBusinessCollaborationAgreementByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Genera el siguiente ID interno del acuerdo en formato de 4 dígitos (0001 a 9999).
     */
    public function getNextAgreementInternalId(?string $companyUuid = null): string
    {
        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid')
                ?? auth()->user()?->companies()->first()?->uuid;
        }

        $query = BusinessCollaborationAgreement::query();
        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        $existingIds = $query->pluck('agreement_internal_id');
        $maxNum = 0;
        foreach ($existingIds as $id) {
            if (preg_match('/^(\d+)$/', (string) $id, $matches)) {
                $val = (int) $matches[1];
                if ($val > $maxNum && $val <= 9999) {
                    $maxNum = $val;
                }
            }
        }

        $next = ($maxNum + 1) > 9999 ? 1 : ($maxNum + 1);

        return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Método createBusinessCollaborationAgreement.
     */
    public function createBusinessCollaborationAgreement(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $agreementInternalId = ! empty($data['agreement_internal_id'])
                ? str_pad((string) substr((string) $data['agreement_internal_id'], -4), 4, '0', STR_PAD_LEFT)
                : $this->getNextAgreementInternalId($data['company_uuid'] ?? null);

            $agreement = BusinessCollaborationAgreement::create([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'],
                'resolution_number' => $data['resolution_number'] ?? null,
                'agreement_internal_id' => $agreementInternalId,
                'contracting_entity_nit' => $data['contracting_entity_nit'],
                'contracting_entity_name' => $data['contracting_entity_name'],
                'effective_date' => $data['effective_date'],
                'expiry_date' => $data['expiry_date'],
                'rep_name' => $data['rep_name'],
                'rep_document_id' => $data['rep_document_id'],
                'transport_modality' => $data['transport_modality'],
                'max_fleet_capacity' => $data['max_fleet_capacity'] ?? 0,
                'status' => $data['status'] ?? 1,
            ]);

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($agreement->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras crear convenio: '.$e->getMessage());
            }

            return $agreement;
        });
    }

    /**
     * Método updateBusinessCollaborationAgreement.
     */
    public function updateBusinessCollaborationAgreement(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'resolution_number' => $data['resolution_number'] ?? $record->resolution_number,
                'agreement_internal_id' => $data['agreement_internal_id'] ?? $record->agreement_internal_id,
                'contracting_entity_nit' => $data['contracting_entity_nit'] ?? $record->contracting_entity_nit,
                'contracting_entity_name' => $data['contracting_entity_name'] ?? $record->contracting_entity_name,
                'effective_date' => $data['effective_date'] ?? $record->effective_date,
                'expiry_date' => $data['expiry_date'] ?? $record->expiry_date,
                'rep_name' => $data['rep_name'] ?? $record->rep_name,
                'rep_document_id' => $data['rep_document_id'] ?? $record->rep_document_id,
                'transport_modality' => $data['transport_modality'] ?? $record->transport_modality,
                'max_fleet_capacity' => $data['max_fleet_capacity'] ?? $record->max_fleet_capacity,
                'status' => $data['status'] ?? $record->status,
            ]);

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras actualizar convenio: '.$e->getMessage());
            }

            return $record->fresh();
        });
    }

    /**
     * Método deleteBusinessCollaborationAgreement.
     */
    public function deleteBusinessCollaborationAgreement(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('BusinessCollaborationAgreementService@deleteBusinessCollaborationAgreement: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método toggleAgreementStatus.
     */
    public function toggleAgreementStatus(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'status' => ! $record->status,
            ]);

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras alternar estado de convenio: '.$e->getMessage());
            }

            return $record->fresh();
        });
    }
}
