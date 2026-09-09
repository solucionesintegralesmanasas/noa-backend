<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\TaxResponsibility;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de ResponsabilidadTributaria.
 *
 * Este servicio gestiona el catálogo de responsabilidades y obligaciones fiscales,
 * permitiendo clasificar correctamente a los contribuyentes según la normativa DIAN.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TaxResponsibilityService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new TaxResponsibility;
    }

    /**
     * Método getAllTaxResponsibilitiesWithPagination.
     */
    public function getAllTaxResponsibilitiesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'name']);
    }

    /**
     * Método getAllTaxResponsibilities.
     */
    public function getAllTaxResponsibilities(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'name']);
    }

    /**
     * Método getTaxResponsibilityByUuid.
     */
    public function getTaxResponsibilityByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createTaxResponsibility.
     */
    public function createTaxResponsibility(array $data): Model
    {
        return $this->transaction(fn () => TaxResponsibility::create([
            'code' => $data['code'],
            'name' => $data['name'],
        ]));
    }

    /**
     * Método updateTaxResponsibility.
     */
    public function updateTaxResponsibility(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var TaxResponsibility $record */
            $record = TaxResponsibility::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteTaxResponsibility.
     */
    public function deleteTaxResponsibility(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('TaxResponsibilityService@deleteTaxResponsibility: '.$e->getMessage());
            throw $e;
        }
    }
}
