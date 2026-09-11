<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Models\Geofence;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Servicio CRUD para la gestión de geocercas.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created 2026-09-10
 */
class GeofenceService extends BaseService
{
    protected array $searchableFields = ['name', 'description'];

    protected function getModelInstance(): Model
    {
        return new Geofence;
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['company']);
    }

    /**
     * Obtiene todas las geocercas de una empresa con paginación.
     */
    public function getGeofencesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): \Illuminate\Pagination\LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Obtiene todas las geocercas activas de una empresa (sin paginación).
     */
    public function getActiveGeofences(string $companyUuid): \Illuminate\Support\Collection
    {
        return $this->query()
            ->where('company_uuid', $companyUuid)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Crea una nueva geocerca.
     */
    public function createGeofence(array $data): Geofence
    {
        return $this->create(array_merge([
            'uuid' => (string) Str::uuid(),
        ], $data));
    }

    /**
     * Actualiza una geocerca existente.
     */
    public function updateGeofence(string $uuid, array $data): bool
    {
        return $this->update($uuid, $data);
    }

    /**
     * Elimina una geocerca.
     */
    public function deleteGeofence(string $uuid): bool
    {
        return $this->delete($uuid);
    }

    /**
     * Alterna el estado activo/inactivo de una geocerca.
     */
    public function toggleGeofenceStatus(string $uuid): bool
    {
        return $this->toggleStatus($uuid, 'is_active');
    }
}