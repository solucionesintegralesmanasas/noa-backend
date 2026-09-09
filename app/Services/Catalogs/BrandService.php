<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\Brand;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de Marca.
 *
 * Este servicio centraliza el catálogo de marcas comerciales, asegurando que
 * todos los vehículos y repuestos estén correctamente categorizados por fabricante.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class BrandService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['description'];

    protected function getModelInstance(): Model
    {
        return new Brand;
    }

    /**
     * Método getAllBrandsWithPagination.
     */
    public function getAllBrandsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'description']);
    }

    /**
     * Método getAllBrands.
     */
    public function getAllBrands(): Collection
    {
        return $this->all(columns: ['uuid', 'description']);
    }

    /**
     * Método getBrandByUuid.
     */
    public function getBrandByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createBrand.
     */
    public function createBrand(array $data): Model
    {
        return $this->transaction(fn () => Brand::create([
            'description' => $data['description'],
        ]));
    }

    /**
     * Método updateBrand.
     */
    public function updateBrand(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var Brand $record */
            $record = Brand::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'description' => $data['description'] ?? $record->description,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteBrand.
     */
    public function deleteBrand(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('BrandService@deleteBrand: '.$e->getMessage());
            throw $e;
        }
    }
}
