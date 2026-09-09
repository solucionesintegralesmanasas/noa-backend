<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\FinancialStatement;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Estados Financieros Anuales.
 *
 * Administra los reportes financieros de las empresas, permitiendo consolidar
 * el balance general y estado de resultados por año fiscal, facilitando el
 * análisis de activos, pasivos, patrimonio e indicadores de utilidad.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FinancialStatementService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new FinancialStatement;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllFinancialStatementsWithPagination.
     */
    public function getAllFinancialStatementsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        $paginator = $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($fs) => [
            'uuid' => $fs->uuid,
            'fiscal_year' => $fs->fiscal_year,
            'currency' => $fs->currency,
            'total_assets' => $fs->total_assets,
            'net_income_period' => $fs->net_income_period,
        ]);

        return $paginator;
    }

    /**
     * Método getAllFinancialStatements.
     */
    public function getAllFinancialStatements(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getFinancialStatementByUuid.
     */
    public function getFinancialStatementByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createFinancialStatement.
     */
    public function createFinancialStatement(array $data): Model
    {
        return $this->transaction(fn () => FinancialStatement::create([
            'company_uuid' => $data['company_uuid'],
            'fiscal_year' => $data['fiscal_year'],
            'currency' => $data['currency'] ?? 'COP',
            'current_assets' => $data['current_assets'] ?? null,
            'inventory' => $data['inventory'] ?? null,
            'total_assets' => $data['total_assets'] ?? null,
            'current_liabilities' => $data['current_liabilities'] ?? null,
            'financial_obligations' => $data['financial_obligations'] ?? null,
            'total_liabilities' => $data['total_liabilities'] ?? null,
            'retained_earnings' => $data['retained_earnings'] ?? null,
            'equity' => $data['equity'] ?? null,
            'operational_income' => $data['operational_income'] ?? null,
            'operating_profit_before_tax' => $data['operating_profit_before_tax'] ?? null,
            'net_income_period' => $data['net_income_period'] ?? null,
            'depreciation_amortization' => $data['depreciation_amortization'] ?? null,
            'financial_expenses' => $data['financial_expenses'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    /**
     * Método updateFinancialStatement.
     */
    public function updateFinancialStatement(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'fiscal_year' => $data['fiscal_year'] ?? $record->fiscal_year,
                'currency' => $data['currency'] ?? $record->currency,
                'current_assets' => $data['current_assets'] ?? $record->current_assets,
                'inventory' => $data['inventory'] ?? $record->inventory,
                'total_assets' => $data['total_assets'] ?? $record->total_assets,
                'current_liabilities' => $data['current_liabilities'] ?? $record->current_liabilities,
                'financial_obligations' => $data['financial_obligations'] ?? $record->financial_obligations,
                'total_liabilities' => $data['total_liabilities'] ?? $record->total_liabilities,
                'retained_earnings' => $data['retained_earnings'] ?? $record->retained_earnings,
                'equity' => $data['equity'] ?? $record->equity,
                'operational_income' => $data['operational_income'] ?? $record->operational_income,
                'operating_profit_before_tax' => $data['operating_profit_before_tax'] ?? $record->operating_profit_before_tax,
                'net_income_period' => $data['net_income_period'] ?? $record->net_income_period,
                'depreciation_amortization' => $data['depreciation_amortization'] ?? $record->depreciation_amortization,
                'financial_expenses' => $data['financial_expenses'] ?? $record->financial_expenses,
                'remarks' => $data['remarks'] ?? $record->remarks,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteFinancialStatement.
     */
    public function deleteFinancialStatement(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }
}
