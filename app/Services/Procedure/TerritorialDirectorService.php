<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\TerritorialDirector;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de DirectorTerritorial.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de DirectorTerritorial en el dominio del negocio.
 * Se encarga de aplicar las políticas corporativas asociadas y mantener la integridad referencial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TerritorialDirectorService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new TerritorialDirector;
    }

    /**
     * Método getAllTerritorialDirectorsWithPagination.
     */
    public function getAllTerritorialDirectorsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllTerritorialDirectors.
     */
    public function getAllTerritorialDirectors(): Collection
    {
        return $this->all();
    }

    /**
     * Método getTerritorialDirectorByUuid.
     */
    public function getTerritorialDirectorByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createTerritorialDirector.
     */
    public function createTerritorialDirector(array $data): Model
    {
        return $this->transaction(fn () => TerritorialDirector::create([
            'name' => $data['name'],
            'territorial_director' => $data['territorial_director'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateTerritorialDirector.
     */
    public function updateTerritorialDirector(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'name' => $data['name'] ?? $record->name,
                'territorial_director' => $data['territorial_director'] ?? $record->territorial_director,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteTerritorialDirector.
     */
    public function deleteTerritorialDirector(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('TerritorialDirectorService@deleteTerritorialDirector: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método toggleTerritorialDirectorStatus.
     */
    public function toggleTerritorialDirectorStatus(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            if (! $this->toggleStatus($uuid, 'is_active')) {
                throw new \RuntimeException('Failed to toggle territorial director status.');
            }

            return $this->findByUuid($uuid);
        });
    }
}
