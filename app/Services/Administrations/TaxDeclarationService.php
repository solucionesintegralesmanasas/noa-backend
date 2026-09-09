<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\TaxDeclaration;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Declaraciones de Renta y Patrimonio.
 *
 * Administra las declaraciones tributarias anuales de las empresas, permitiendo
 * registrar el patrimonio bruto, ingresos brutos y renta líquida, asegurando que el
 * cumplimiento fiscal esté debidamente documentado en el sistema.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TaxDeclarationService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['declaration_type'];

    protected function getModelInstance(): Model
    {
        return new TaxDeclaration;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllTaxDeclarationsWithPagination.
     */
    public function getAllTaxDeclarationsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllTaxDeclarations.
     */
    public function getAllTaxDeclarations(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getTaxDeclarationByUuid.
     */
    public function getTaxDeclarationByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createTaxDeclaration.
     */
    public function createTaxDeclaration(array $data): Model
    {
        return $this->transaction(fn () => TaxDeclaration::create([
            'company_uuid' => $data['company_uuid'],
            'fiscal_year' => $data['fiscal_year'],
            'gross_assets' => $data['gross_assets'] ?? null,
            'net_assets' => $data['net_assets'] ?? null,
            'total_gross_income' => $data['total_gross_income'] ?? null,
            'ordinary_net_income' => $data['ordinary_net_income'] ?? null,
            'pre_tax_net_profit' => $data['pre_tax_net_profit'] ?? null,
            'total_operating_non_operating_income' => $data['total_operating_non_operating_income'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => $data['status'] ?? null,
        ]));
    }

    /**
     * Método updateTaxDeclaration.
     */
    public function updateTaxDeclaration(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'fiscal_year' => $data['fiscal_year'] ?? $record->fiscal_year,
                'gross_assets' => $data['gross_assets'] ?? $record->gross_assets,
                'net_assets' => $data['net_assets'] ?? $record->net_assets,
                'total_gross_income' => $data['total_gross_income'] ?? $record->total_gross_income,
                'ordinary_net_income' => $data['ordinary_net_income'] ?? $record->ordinary_net_income,
                'pre_tax_net_profit' => $data['pre_tax_net_profit'] ?? $record->pre_tax_net_profit,
                'total_operating_non_operating_income' => $data['total_operating_non_operating_income'] ?? $record->total_operating_non_operating_income,
                'remarks' => $data['remarks'] ?? $record->remarks,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteTaxDeclaration.
     */
    public function deleteTaxDeclaration(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }

    /**
     * Método toggleTaxDeclarationStatus.
     */
    public function toggleTaxDeclarationStatus(string $uuid): Model
    {
        try {
            $this->toggleStatus($uuid, 'status');

            return $this->findByUuid($uuid, relations: self::RELATIONS);
        } catch (\Exception $e) {
            Logger::error('TaxDeclarationService@toggleTaxDeclarationStatus: '.$e->getMessage());
            throw $e;
        }
    }
}
