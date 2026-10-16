<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\FleetServiceContract;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de ContratoGestionFlota.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de ContratoGestionFlota en el dominio del negocio.
 * Se encarga de la gestión de contratos de servicios de flota, controlando vigencias,
 * tipos de contrato y tasaciones económicas.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class FleetServiceContractService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['contract_number', 'contract_type', 'type_of_action'];

    protected function getModelInstance(): Model
    {
        return new FleetServiceContract;
    }

    /**
     * Método getAllFleetServiceContractsWithPagination.
     */
    public function getAllFleetServiceContractsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllFleetServiceContracts.
     */
    public function getAllFleetServiceContracts(): Collection
    {
        return $this->all();
    }

    /**
     * Método getFleetServiceContractByUuid.
     */
    public function getFleetServiceContractByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createFleetServiceContract.
     */
    public function createFleetServiceContract(array $data): Model
    {
        return $this->transaction(fn () => FleetServiceContract::create([
            'procedure_uuid' => $data['procedure_uuid'],
            'company_uuid' => $data['company_uuid'] ?? null,
            'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
            'third_party_uuid' => $data['third_party_uuid'] ?? null,
            'item' => $data['item'] ?? 1,
            'type_of_action' => $data['type_of_action'] ?? 'C',
            'issue_date' => $data['issue_date'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'duration' => $data['duration'],
            'contract_type' => $data['contract_type'] ?? '1',
            'contract_number' => $data['contract_number'],
            'signature_validation' => $data['signature_validation'] ?? 'S',
            'valuation_amount' => $data['valuation_amount'] ?? 0,
        ]));
    }

    /**
     * Método updateFleetServiceContract.
     */
    public function updateFleetServiceContract(string $uuid, array $data): ?Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            if (! $record) {
                return null;
            }

            $record->update([
                'procedure_uuid' => $data['procedure_uuid'] ?? $record->procedure_uuid,
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'item' => $data['item'] ?? $record->item,
                'type_of_action' => $data['type_of_action'] ?? $record->type_of_action,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'start_date' => $data['start_date'] ?? $record->start_date,
                'end_date' => $data['end_date'] ?? $record->end_date,
                'duration' => $data['duration'] ?? $record->duration,
                'contract_type' => $data['contract_type'] ?? $record->contract_type,
                'contract_number' => $data['contract_number'] ?? $record->contract_number,
                'signature_validation' => $data['signature_validation'] ?? $record->signature_validation,
                'valuation_amount' => $data['valuation_amount'] ?? $record->valuation_amount,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteFleetServiceContract.
     */
    public function deleteFleetServiceContract(string $uuid): bool
    {
        return parent::delete($uuid);
    }
}
