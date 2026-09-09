<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\BillingResolutionType;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de TipoResolucionFacturacion.
 *
 * Este servicio clasifica los documentos electrónicos que pueden ser amparados
 * por resoluciones de facturación autorizadas por la DIAN.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class BillingResolutionTypeService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new BillingResolutionType;
    }

    /**
     * Método getAllBillingResolutionTypesWithPagination.
     */
    public function getAllBillingResolutionTypesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'name']);
    }

    /**
     * Método getAllBillingResolutionTypes.
     */
    public function getAllBillingResolutionTypes(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'name']);
    }

    /**
     * Método getBillingResolutionTypeByUuid.
     */
    public function getBillingResolutionTypeByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createBillingResolutionType.
     */
    public function createBillingResolutionType(array $data): Model
    {
        return $this->transaction(fn () => BillingResolutionType::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateBillingResolutionType.
     */
    public function updateBillingResolutionType(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var BillingResolutionType $record */
            $record = BillingResolutionType::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteBillingResolutionType.
     */
    public function deleteBillingResolutionType(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('BillingResolutionTypeService@deleteBillingResolutionType: '.$e->getMessage());
            throw $e;
        }
    }
}
