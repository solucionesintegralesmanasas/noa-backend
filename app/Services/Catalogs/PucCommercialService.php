<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\PucCommercial;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de PucComercial.
 *
 * Este servicio gestiona el Plan Único de Cuentas, permitiendo organizar la estructura
 * contable de la empresa y asegurar que las cuentas cumplan con la naturaleza requerida.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class PucCommercialService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new PucCommercial;
    }

    /**
     * Método getAllPucCommercialsWithPagination.
     */
    public function getAllPucCommercialsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'description']);
    }

    /**
     * Método getAllPucCommercials.
     */
    public function getAllPucCommercials(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'description']);
    }

    /**
     * Método getPucCommercialByUuid.
     */
    public function getPucCommercialByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createPucCommercial.
     */
    public function createPucCommercial(array $data): Model
    {
        return $this->transaction(fn () => PucCommercial::create([
            'code' => $data['code'],
            'level' => $data['level'],
            'description' => $data['description'],
            'nature' => $data['nature'],
            'account_class' => $data['account_class'],
            'is_reductive' => $data['is_reductive'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updatePucCommercial.
     */
    public function updatePucCommercial(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var PucCommercial $record */
            $record = PucCommercial::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'level' => $data['level'] ?? $record->level,
                'description' => $data['description'] ?? $record->description,
                'nature' => $data['nature'] ?? $record->nature,
                'account_class' => $data['account_class'] ?? $record->account_class,
                'is_reductive' => $data['is_reductive'] ?? $record->is_reductive,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deletePucCommercial.
     */
    public function deletePucCommercial(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('PucCommercialService@deletePucCommercial: '.$e->getMessage());
            throw $e;
        }
    }
}
