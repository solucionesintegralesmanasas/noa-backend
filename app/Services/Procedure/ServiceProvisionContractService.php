<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\ServiceProvisionContract;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio para la gestión de Contratos de Prestación de Servicios.
 */
class ServiceProvisionContractService extends BaseService
{
    protected array $searchableFields = ['contract_number', 'status'];

    protected function getModelInstance(): Model
    {
        return new ServiceProvisionContract;
    }

    public function getAllWithPagination(int $perPage = 15, int $page = 1, string $search = '', ?string $companyUuid = null): LengthAwarePaginator
    {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    public function getAll(): Collection
    {
        return $this->all();
    }

    public function getByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    public function create(array $data): Model
    {
        return $this->transaction(fn () => ServiceProvisionContract::create([
            'company_uuid' => $data['company_uuid'] ?? null,
            'procedure_uuid' => $data['procedure_uuid'] ?? null,
            'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
            'third_party_uuid' => $data['third_party_uuid'] ?? null,
            'contract_number' => $data['contract_number'],
            'issue_date' => $data['issue_date'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration' => $data['duration'],
            'valuation_amount' => $data['valuation_amount'] ?? 0,
            'coverage' => $data['coverage'] ?? 'NACIONAL',
            'object_description' => $data['object_description'] ?? null,
            'status' => $data['status'] ?? 'BORRADOR',
            'document_hash' => $data['document_hash'] ?? null,
        ]));
    }

    public function update(string $uuid, array $data): bool
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            if (! $record) {
                return false;
            }
            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'procedure_uuid' => $data['procedure_uuid'] ?? $record->procedure_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'contract_number' => $data['contract_number'] ?? $record->contract_number,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'start_date' => $data['start_date'] ?? $record->start_date,
                'end_date' => $data['end_date'] ?? $record->end_date,
                'duration' => $data['duration'] ?? $record->duration,
                'valuation_amount' => $data['valuation_amount'] ?? $record->valuation_amount,
                'coverage' => $data['coverage'] ?? $record->coverage,
                'object_description' => $data['object_description'] ?? $record->object_description,
                'status' => $data['status'] ?? $record->status,
                'document_hash' => $data['document_hash'] ?? $record->document_hash,
            ]);
            return true;
        });
    }

    public function delete(string $uuid): bool
    {
        return parent::delete($uuid);
    }
}