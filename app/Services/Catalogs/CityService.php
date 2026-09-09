<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\City;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de Ciudad.
 *
 * Este servicio gestiona el catálogo de municipios y ciudades, permitiendo la vinculación
 * con sus respectivos departamentos y manteniendo la integridad de la ubicación geográfica.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class CityService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new City;
    }

    /**
     * Método getAllCitiesWithPagination.
     */
    public function getAllCitiesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'dane_code', 'name']);
    }

    /**
     * Método getAllCities.
     */
    public function getAllCities(): Collection
    {
        return $this->all(columns: ['uuid', 'dane_code', 'name']);
    }

    /**
     * Método getCityByUuid.
     */
    public function getCityByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createCity.
     */
    public function createCity(array $data): Model
    {
        return $this->transaction(fn () => City::create([
            'department_uuid' => $data['department_uuid'],
            'dane_code' => $data['dane_code'],
            'name' => $data['name'],
        ]));
    }

    /**
     * Método updateCity.
     */
    public function updateCity(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var City $record */
            $record = City::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'department_uuid' => $data['department_uuid'] ?? $record->department_uuid,
                'dane_code' => $data['dane_code'] ?? $record->dane_code,
                'name' => $data['name'] ?? $record->name,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteCity.
     */
    public function deleteCity(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('CityService@deleteCity: '.$e->getMessage());
            throw $e;
        }
    }
}
