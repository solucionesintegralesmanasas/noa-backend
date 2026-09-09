<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\TaxType;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de TipoImpuesto.
 *
 * Este servicio configura las tarifas y cuentas contables de los impuestos nacionales
 * y locales, permitiendo la automatización tributaria en los procesos de compra y venta.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class TaxTypeService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new TaxType;
    }

    /**
     * Método getAllTaxTypesWithPagination.
     */
    public function getAllTaxTypesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'name']);
    }

    /**
     * Método getAllTaxTypes.
     */
    public function getAllTaxTypes(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'name']);
    }

    /**
     * Método getTaxTypeByUuid.
     */
    public function getTaxTypeByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createTaxType.
     */
    public function createTaxType(array $data): Model
    {
        return $this->transaction(fn () => TaxType::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'rate' => $data['rate'],
            'debit_account' => $data['debit_account'] ?? null,
            'credit_account' => $data['credit_account'] ?? null,
            'applies_sales' => $data['applies_sales'] ?? true,
            'applies_purchases' => $data['applies_purchases'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateTaxType.
     */
    public function updateTaxType(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var TaxType $record */
            $record = TaxType::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
                'type' => $data['type'] ?? $record->type,
                'rate' => $data['rate'] ?? $record->rate,
                'debit_account' => $data['debit_account'] ?? $record->debit_account,
                'credit_account' => $data['credit_account'] ?? $record->credit_account,
                'applies_sales' => $data['applies_sales'] ?? $record->applies_sales,
                'applies_purchases' => $data['applies_purchases'] ?? $record->applies_purchases,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteTaxType.
     */
    public function deleteTaxType(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('TaxTypeService@deleteTaxType: '.$e->getMessage());
            throw $e;
        }
    }
}
