<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\TaxRegime;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de RegimenFiscal.
 *
 * Este servicio administra los regímenes tributarios definidos por la DIAN,
 * fundamentales para la correcta configuración de impuestos y obligaciones de los terceros.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TaxRegimeService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new TaxRegime;
    }

    /**
     * Método getAllTaxRegimesWithPagination.
     */
    public function getAllTaxRegimesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'name']);
    }

    /**
     * Método getAllTaxRegimes.
     */
    public function getAllTaxRegimes(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'name']);
    }

    /**
     * Método getTaxRegimeByUuid.
     */
    public function getTaxRegimeByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createTaxRegime.
     */
    public function createTaxRegime(array $data): Model
    {
        return $this->transaction(fn () => TaxRegime::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateTaxRegime.
     */
    public function updateTaxRegime(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var TaxRegime $record */
            $record = TaxRegime::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
                'description' => $data['description'] ?? $record->description,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteTaxRegime.
     */
    public function deleteTaxRegime(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('TaxRegimeService@deleteTaxRegime: '.$e->getMessage());
            throw $e;
        }
    }
}
