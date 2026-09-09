<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\Experience;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Experiencias y Contratos Previos.
 *
 * Administra el historial de contratos y experiencias comerciales de las empresas,
 * permitiendo registrar el cliente, valor del contrato, fechas de ejecución y
 * estados de vigencia, fundamental para procesos de licitación y calificación.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ExperienceService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['contract_number'];

    protected function getModelInstance(): Model
    {
        return new Experience;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllExperiencesWithPagination.
     */
    public function getAllExperiencesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllExperiences.
     */
    public function getAllExperiences(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getExperienceByUuid.
     */
    public function getExperienceByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createExperience.
     */
    public function createExperience(array $data): Model
    {
        return $this->transaction(fn () => Experience::create([
            'company_uuid' => $data['company_uuid'],
            'customer_name' => $data['customer_name'],
            'value_before_tax' => $data['value_before_tax'] ?? null,
            'currency' => $data['currency'] ?? 'COP',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_ongoing' => $data['is_ongoing'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    /**
     * Método updateExperience.
     */
    public function updateExperience(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'customer_name' => $data['customer_name'] ?? $record->customer_name,
                'value_before_tax' => $data['value_before_tax'] ?? $record->value_before_tax,
                'currency' => $data['currency'] ?? $record->currency,
                'start_date' => $data['start_date'] ?? $record->start_date,
                'end_date' => $data['end_date'] ?? $record->end_date,
                'is_ongoing' => $data['is_ongoing'] ?? $record->is_ongoing,
                'remarks' => $data['remarks'] ?? $record->remarks,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteExperience.
     */
    public function deleteExperience(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }
}
