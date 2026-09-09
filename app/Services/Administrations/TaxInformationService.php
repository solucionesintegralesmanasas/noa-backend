<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\TaxInformation;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Información Tributaria y Fiscal.
 *
 * Administra el perfil tributario complementario de las empresas, incluyendo
 * su clasificación por tamaño, régimen especial aplicable y cumplimiento
 * de obligaciones como agente de retención.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class TaxInformationService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['tax_special_regime'];

    protected function getModelInstance(): Model
    {
        return new TaxInformation;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getTaxInformationByUuid.
     */
    public function getTaxInformationByUuid(string $uuid): Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método getAllTaxInformationsWithPagination.
     */
    public function getAllTaxInformationsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllTaxInformations.
     */
    public function getAllTaxInformations(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método createTaxInformation.
     */
    public function createTaxInformation(array $data): Model
    {
        return $this->transaction(fn () => TaxInformation::create([
            'company_uuid' => $data['company_uuid'],
            'is_withholding_agent_exempt' => $data['is_withholding_agent_exempt'] ?? null,
            'tax_special_regime' => $data['tax_special_regime'] ?? null,
            'company_size' => $data['company_size'] ?? null,
            'financial_statements_path' => $data['financial_statements_path'] ?? null,
            'company_size_certificate_path' => $data['company_size_certificate_path'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    /**
     * Método updateTaxInformation.
     */
    public function updateTaxInformation(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'is_withholding_agent_exempt' => $data['is_withholding_agent_exempt'] ?? $record->is_withholding_agent_exempt,
                'tax_special_regime' => $data['tax_special_regime'] ?? $record->tax_special_regime,
                'company_size' => $data['company_size'] ?? $record->company_size,
                'financial_statements_path' => $data['financial_statements_path'] ?? $record->financial_statements_path,
                'company_size_certificate_path' => $data['company_size_certificate_path'] ?? $record->company_size_certificate_path,
                'remarks' => $data['remarks'] ?? $record->remarks,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteTaxInformation.
     */
    public function deleteTaxInformation(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }
}
