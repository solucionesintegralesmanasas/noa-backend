<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\Owner;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Propietario.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class OwnerService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['owner_name', 'document_number'];

    protected function getModelInstance(): Model
    {
        return new Owner;
    }

    /**
     * Método getAllOwnersWithPagination.
     */
    public function getAllOwnersWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($companyUuid) {
            $query->whereHas('vehicle', function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            });
        }

        if ($thirdPartyUuid) {
            $query->where('third_party_uuid', $thirdPartyUuid);
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Método getAllOwners.
     */
    public function getAllOwners(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

        if ($companyUuid) {
            $query->whereHas('vehicle', function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            });
        }

        if ($thirdPartyUuid) {
            $query->where('third_party_uuid', $thirdPartyUuid);
        }

        return $query->get();
    }

    /**
     * Método getOwnerByUuid.
     */
    public function getOwnerByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createOwner.
     */
    public function createOwner(array $data): Model
    {
        return $this->transaction(fn () => Owner::create([
            'third_party_uuid' => $data['third_party_uuid'] ?? null,
            'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
            'document_type_uuid' => $data['document_type_uuid'],
            'owner_name' => $data['owner_name'],
            'document_number' => $data['document_number'],
            'verification_digit' => $data['verification_digit'] ?? null,
        ]));
    }

    /**
     * Método updateOwner.
     */
    public function updateOwner(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'document_type_uuid' => $data['document_type_uuid'] ?? $record->document_type_uuid,
                'owner_name' => $data['owner_name'] ?? $record->owner_name,
                'document_number' => $data['document_number'] ?? $record->document_number,
                'verification_digit' => $data['verification_digit'] ?? $record->verification_digit,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteOwner.
     */
    public function deleteOwner(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('OwnerService@deleteOwner: '.$e->getMessage());
            throw $e;
        }
    }
}
