<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\DriverLicense;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de LicenciaConduccion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class DriverLicenseService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['document_number'];

    public function __construct(
        private readonly OwnerDriverService $ownerDriverService
    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new DriverLicense;
    }

    /**
     * Método query.
     */
    public function query(): Builder
    {
        return parent::query()->with('ownerDriver');
    }

    /**
     * Método getAllDriverLicensesWithPagination.
     */
    public function getAllDriverLicensesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()->with('ownerDriver');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

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
     * Método getAllDriverLicenses.
     */
    public function getAllDriverLicenses(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query()->with('ownerDriver');

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($thirdPartyUuid) {
            $query->where('third_party_uuid', $thirdPartyUuid);
        }

        return $query->get();
    }

    /**
     * Método getDriverLicenseByUuid.
     */
    public function getDriverLicenseByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createDriverLicense.
     */
    public function createDriverLicense(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $license = DriverLicense::create([
                'company_uuid' => $data['company_uuid'],
                'third_party_uuid' => $data['third_party_uuid'],
                'number' => $data['number'],
                'category' => $data['category'],
                'issue_date' => $data['issue_date'],
                'expiration_date' => $data['expiration_date'],
                'restrictions' => $data['restrictions'] ?? null,
                'status' => $data['status'] ?? 'ACTIVA',
            ]);

            $this->ownerDriverService->createOwnerDriver([
                'company_uuid' => $license->company_uuid,
                'driver_license_uuid' => $license->uuid,
                'third_party_uuid' => $data['affiliate_uuid'] ?? $license->third_party_uuid, // Affiliate UUID
            ]);

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($license->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras crear licencia: '.$e->getMessage());
            }

            return $license->load('ownerDriver');
        });
    }

    /**
     * Método updateDriverLicense.
     */
    public function updateDriverLicense(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'number' => $data['number'] ?? $record->number,
                'category' => $data['category'] ?? $record->category,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'expiration_date' => $data['expiration_date'] ?? $record->expiration_date,
                'restrictions' => $data['restrictions'] ?? $record->restrictions,
                'status' => $data['status'] ?? $record->status,
            ]);

            $targetAffiliateUuid = $data['affiliate_uuid'] ?? $record->third_party_uuid;
            if ($targetAffiliateUuid) {
                if ($record->ownerDriver) {
                    $this->ownerDriverService->updateOwnerDriver($record->ownerDriver->uuid, [
                        'third_party_uuid' => $targetAffiliateUuid,
                    ]);
                } else {
                    $this->ownerDriverService->createOwnerDriver([
                        'company_uuid' => $record->company_uuid,
                        'driver_license_uuid' => $record->uuid,
                        'third_party_uuid' => $targetAffiliateUuid,
                    ]);
                }
            }

            try {
                app(\App\Services\Notifications\NotificationsService::class)->syncNotifications($record->company_uuid);
            } catch (\Exception $e) {
                Logger::error('Error al sincronizar notificaciones tras actualizar licencia: '.$e->getMessage());
            }

            return $record->fresh()->load('ownerDriver');
        });
    }

    /**
     * Método deleteDriverLicense.
     */
    public function deleteDriverLicense(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('DriverLicenseService@deleteDriverLicense: '.$e->getMessage());
            throw $e;
        }
    }
}
