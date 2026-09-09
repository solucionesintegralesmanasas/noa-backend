<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\InspectionResult;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de ResultadoInspeccion.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class InspectionResultService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['observations'];

    protected function getModelInstance(): Model
    {
        return new InspectionResult;
    }

    /**
     * Método getAllInspectionResultsWithPagination.
     */
    public function getAllInspectionResultsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $thirdPartyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($companyUuid) {
            $query->whereHas('inspection', function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            });
        }

        if ($thirdPartyUuid) {
            $query->whereHas('inspection.vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Método getAllInspectionResults.
     */
    public function getAllInspectionResults(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

        if ($companyUuid) {
            $query->whereHas('inspection', function ($q) use ($companyUuid) {
                $q->where('company_uuid', $companyUuid);
            });
        }

        if ($thirdPartyUuid) {
            $query->whereHas('inspection.vehicle', function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid);
            });
        }

        return $query->get();
    }

    /**
     * Método getInspectionResultByCompoundKey.
     */
    public function getInspectionResultByCompoundKey(string $inspectionUuid, string $itemUuid): ?Model
    {
        return InspectionResult::query()
            ->where('inspection_uuid', $inspectionUuid)
            ->where('item_uuid', $itemUuid)
            ->first();
    }

    /**
     * Método createInspectionResult.
     */
    public function createInspectionResult(array $data): Model
    {
        return $this->transaction(fn () => InspectionResult::create([
            'inspection_uuid' => $data['inspection_uuid'],
            'item_uuid' => $data['item_uuid'],
            'is_selected' => $data['is_selected'] ?? 1,
            'status' => $data['status'] ?? 'APROBADO',
            'observations' => $data['observations'] ?? null,
        ]));
    }

    /**
     * Método updateInspectionResult.
     */
    public function updateInspectionResult(string $inspectionUuid, string $itemUuid, array $data): Model
    {
        return $this->transaction(function () use ($inspectionUuid, $itemUuid, $data) {
            $record = InspectionResult::query()
                ->where('inspection_uuid', $inspectionUuid)
                ->where('item_uuid', $itemUuid)
                ->firstOrFail();

            $record->update([
                'is_selected' => $data['is_selected'] ?? $record->is_selected,
                'status' => $data['status'] ?? $record->status,
                'observations' => $data['observations'] ?? $record->observations,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteInspectionResult.
     */
    public function deleteInspectionResult(string $inspectionUuid, string $itemUuid): void
    {
        try {
            $record = $this->getInspectionResultByCompoundKey($inspectionUuid, $itemUuid);
            if ($record) {
                $record->delete();
            }
        } catch (\Exception $e) {
            Logger::error('InspectionResultService@deleteInspectionResult: '.$e->getMessage());
            throw $e;
        }
    }
}
