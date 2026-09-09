<?php

declare(strict_types=1);

namespace App\Services\ContractExtraction;

use App\Models\ObjectsContract;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Objetos de Contratos.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de Objetos de Contratos en el dominio del negocio.
 * Se encarga de aplicar las políticas corporativas asociadas y mantener la integridad referencial.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ObjectContractService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new ObjectsContract;
    }

    /**
     * Método getAllObjectContractsWithPagination.
     */
    public function getAllObjectContractsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllObjectContracts.
     */
    public function getAllObjectContracts(): Collection
    {
        return $this->all();
    }

    /**
     * Método getObjectContractByUuid.
     */
    public function getObjectContractByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createObjectContract.
     */
    public function createObjectContract(array $data): Model
    {
        return $this->transaction(fn () => ObjectsContract::create([
            'name' => $data['name'],
            'description' => $data['description'],
        ]));
    }

    /**
     * Método updateObjectContract.
     */
    public function updateObjectContract(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var ObjectsContract $record */
            $record = ObjectsContract::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'name' => $data['name'] ?? $record->name,
                'description' => $data['description'] ?? $record->description,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteObjectContract.
     */
    public function deleteObjectContract(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ObjectContractService@deleteObjectContract: '.$e->getMessage());
            throw $e;
        }
    }
}
