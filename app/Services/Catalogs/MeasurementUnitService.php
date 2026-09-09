<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\MeasurementUnit;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de UnidadMedida.
 *
 * Este servicio administra las unidades de medida estandarizadas, fundamentales
 * para la consistencia en el manejo de inventarios y la facturación electrónica.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class MeasurementUnitService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new MeasurementUnit;
    }

    /**
     * Método getAllMeasurementUnitsWithPagination.
     */
    public function getAllMeasurementUnitsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'code', 'name']);
    }

    /**
     * Método getAllMeasurementUnits.
     */
    public function getAllMeasurementUnits(): Collection
    {
        return $this->all(columns: ['uuid', 'code', 'name']);
    }

    /**
     * Método getMeasurementUnitByUuid.
     */
    public function getMeasurementUnitByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createMeasurementUnit.
     */
    public function createMeasurementUnit(array $data): Model
    {
        return $this->transaction(fn () => MeasurementUnit::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updateMeasurementUnit.
     */
    public function updateMeasurementUnit(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var MeasurementUnit $record */
            $record = MeasurementUnit::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'code' => $data['code'] ?? $record->code,
                'name' => $data['name'] ?? $record->name,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteMeasurementUnit.
     */
    public function deleteMeasurementUnit(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('MeasurementUnitService@deleteMeasurementUnit: '.$e->getMessage());
            throw $e;
        }
    }
}
