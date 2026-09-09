<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\Tribute;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de Tributo.
 *
 * Este servicio administra el catálogo de tributos DIAN, permitiendo su correcta
 * aplicación y discriminación en los documentos electrónicos generados por el sistema.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TributeService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new Tribute;
    }

    /**
     * Método getAllTributesWithPagination.
     */
    public function getAllTributesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'dian_code', 'name']);
    }

    /**
     * Método getAllTributes.
     */
    public function getAllTributes(): Collection
    {
        return $this->all(columns: ['uuid', 'dian_code', 'name']);
    }

    /**
     * Método getTributeByUuid.
     */
    public function getTributeByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createTribute.
     */
    public function createTribute(array $data): Model
    {
        return $this->transaction(fn () => Tribute::create([
            'dian_code' => $data['dian_code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateTribute.
     */
    public function updateTribute(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var Tribute $record */
            $record = Tribute::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'dian_code' => $data['dian_code'] ?? $record->dian_code,
                'name' => $data['name'] ?? $record->name,
                'description' => $data['description'] ?? $record->description,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteTribute.
     */
    public function deleteTribute(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('TributeService@deleteTribute: '.$e->getMessage());
            throw $e;
        }
    }
}
