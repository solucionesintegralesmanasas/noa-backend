<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\OwnerDriver;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de ConductorPropietario.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class OwnerDriverService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['document_number'];

    protected function getModelInstance(): Model
    {
        return new OwnerDriver;
    }

    /**
     * Método getAllOwnerDriversWithPagination.
     */
    public function getAllOwnerDriversWithPagination(
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
     * Método getAllOwnerDrivers.
     */
    public function getAllOwnerDrivers(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->where('third_party_uuid', $thirdPartyUuid);
        }

        return $query->get();
    }

    /**
     * Método getOwnerDriverByUuid.
     */
    public function getOwnerDriverByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createOwnerDriver.
     */
    public function createOwnerDriver(array $data): Model
    {
        return $this->transaction(fn () => OwnerDriver::create([
            'company_uuid' => $data['company_uuid'],
            'driver_license_uuid' => $data['driver_license_uuid'],
            'third_party_uuid' => $data['third_party_uuid'],
        ]));
    }

    /**
     * Método updateOwnerDriver.
     */
    public function updateOwnerDriver(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'driver_license_uuid' => $data['driver_license_uuid'] ?? $record->driver_license_uuid,
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteOwnerDriver.
     */
    public function deleteOwnerDriver(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('OwnerDriverService@deleteOwnerDriver: '.$e->getMessage());
            throw $e;
        }
    }
}
