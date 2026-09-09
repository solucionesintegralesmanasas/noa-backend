<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\InspectionItem;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de ItemInspeccion.
 *
 * Este servicio administra los ítems de control para las inspecciones vehiculares,
 * permitiendo definir y organizar los puntos de verificación por categorías técnicas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class InspectionItemService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new InspectionItem;
    }

    /**
     * Método getAllInspectionItemsWithPagination.
     */
    public function getAllInspectionItemsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'category', 'item_name']);
    }

    /**
     * Método getAllInspectionItems.
     */
    public function getAllInspectionItems(): Collection
    {
        return $this->all(columns: ['uuid', 'category', 'item_name']);
    }

    /**
     * Método getInspectionItemByUuid.
     */
    public function getInspectionItemByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createInspectionItem.
     */
    public function createInspectionItem(array $data): Model
    {
        return $this->transaction(fn () => InspectionItem::create([
            'category' => $data['category'],
            'item_name' => $data['item_name'],
            'description' => $data['description'] ?? null,
        ]));
    }

    /**
     * Método updateInspectionItem.
     */
    public function updateInspectionItem(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var InspectionItem $record */
            $record = InspectionItem::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'category' => $data['category'] ?? $record->category,
                'item_name' => $data['item_name'] ?? $record->item_name,
                'description' => $data['description'] ?? $record->description,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteInspectionItem.
     */
    public function deleteInspectionItem(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('InspectionItemService@deleteInspectionItem: '.$e->getMessage());
            throw $e;
        }
    }
}
