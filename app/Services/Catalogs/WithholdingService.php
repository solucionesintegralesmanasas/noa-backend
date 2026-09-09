<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\Withholding;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de Retencion.
 *
 * Este servicio administra las retenciones tributarias, permitiendo configurar
 * bases mínimas y tarifas para el cumplimiento de las obligaciones fiscales de la empresa.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class WithholdingService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new Withholding;
    }

    /**
     * Método getAllWithholdingsWithPagination.
     */
    public function getAllWithholdingsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'name']);
    }

    /**
     * Método getAllWithholdings.
     */
    public function getAllWithholdings(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'name']);
    }

    /**
     * Método getWithholdingByUuid.
     */
    public function getWithholdingByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createWithholding.
     */
    public function createWithholding(array $data): Model
    {
        return $this->transaction(fn () => Withholding::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'dian_concept' => $data['dian_concept'] ?? null,
            'base_minimum' => $data['base_minimum'] ?? 0.00,
            'rate' => $data['rate'],
            'debit_account' => $data['debit_account'] ?? null,
            'credit_account' => $data['credit_account'] ?? null,
            'applies_purchases' => $data['applies_purchases'] ?? true,
            'applies_sales' => $data['applies_sales'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateWithholding.
     */
    public function updateWithholding(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var Withholding $record */
            $record = Withholding::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
                'type' => $data['type'] ?? $record->type,
                'dian_concept' => $data['dian_concept'] ?? $record->dian_concept,
                'base_minimum' => $data['base_minimum'] ?? $record->base_minimum,
                'rate' => $data['rate'] ?? $record->rate,
                'debit_account' => $data['debit_account'] ?? $record->debit_account,
                'credit_account' => $data['credit_account'] ?? $record->credit_account,
                'applies_purchases' => $data['applies_purchases'] ?? $record->applies_purchases,
                'applies_sales' => $data['applies_sales'] ?? $record->applies_sales,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteWithholding.
     */
    public function deleteWithholding(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('WithholdingService@deleteWithholding: '.$e->getMessage());
            throw $e;
        }
    }
}
