<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\DianParameter;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de ParametroDian.
 *
 * Este servicio administra los valores tributarios y económicos de referencia anual,
 * permitiendo que el sistema realice cálculos fiscales precisos basados en la normativa.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class DianParameterService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new DianParameter;
    }

    /**
     * Método getAllDianParametersWithPagination.
     */
    public function getAllDianParametersWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'year', 'uvt']);
    }

    /**
     * Método getAllDianParameters.
     */
    public function getAllDianParameters(): Collection
    {
        return $this->all(columns: ['uuid', 'year', 'uvt']);
    }

    /**
     * Método getDianParameterByUuid.
     */
    public function getDianParameterByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createDianParameter.
     */
    public function createDianParameter(array $data): Model
    {
        return $this->transaction(fn () => DianParameter::create([
            'year' => $data['year'],
            'uvt' => $data['uvt'],
            'iva_withholding_rate' => $data['iva_withholding_rate'] ?? 0.15,
            'minimum_wage' => $data['minimum_wage'] ?? null,
            'transport_subsidy' => $data['transport_subsidy'] ?? null,
            'usury_rate' => $data['usury_rate'] ?? null,
            'observations' => $data['observations'] ?? null,
        ]));
    }

    /**
     * Método updateDianParameter.
     */
    public function updateDianParameter(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var DianParameter $record */
            $record = DianParameter::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'year' => $data['year'] ?? $record->year,
                'uvt' => $data['uvt'] ?? $record->uvt,
                'iva_withholding_rate' => $data['iva_withholding_rate'] ?? $record->iva_withholding_rate,
                'minimum_wage' => $data['minimum_wage'] ?? $record->minimum_wage,
                'transport_subsidy' => $data['transport_subsidy'] ?? $record->transport_subsidy,
                'usury_rate' => $data['usury_rate'] ?? $record->usury_rate,
                'observations' => $data['observations'] ?? $record->observations,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteDianParameter.
     */
    public function deleteDianParameter(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('DianParameterService@deleteDianParameter: '.$e->getMessage());
            throw $e;
        }
    }
}
