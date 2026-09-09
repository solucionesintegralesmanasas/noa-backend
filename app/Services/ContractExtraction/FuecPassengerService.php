<?php

declare(strict_types=1);

namespace App\Services\ContractExtraction;

use App\Models\FuecPassenger;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Pasajeros de FUEC.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de Pasajeros de FUEC en el dominio del negocio.
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
class FuecPassengerService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['first_and_last_name', 'document_number'];

    protected function getModelInstance(): Model
    {
        return new FuecPassenger;
    }

    /**
     * Método getAllFuecPassengersWithPagination.
     */
    public function getAllFuecPassengersWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllFuecPassengers.
     */
    public function getAllFuecPassengers(): Collection
    {
        return $this->all();
    }

    /**
     * Método getFuecPassengerByUuid.
     */
    public function getFuecPassengerByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createFuecPassenger.
     */
    public function createFuecPassenger(array $data): Model
    {
        return $this->transaction(function () use ($data) {

            return FuecPassenger::create([
                'fuec_uuid' => $data['fuec_uuid'],
                'type_of_document_uuid' => $data['type_of_document_uuid'],
                'document_number' => $data['document_number'],
                'first_and_last_name' => $data['first_and_last_name'],
            ]);
        });
    }

    /**
     * Método updateFuecPassenger.
     */
    public function updateFuecPassenger(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var FuecPassenger $record */
            $record = FuecPassenger::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'fuec_uuid' => $data['fuec_uuid'] ?? $record->fuec_uuid,
                'type_of_document_uuid' => $data['type_of_document_uuid'] ?? $record->type_of_document_uuid,
                'document_number' => $data['document_number'] ?? $record->document_number,
                'first_and_last_name' => $data['first_and_last_name'] ?? $record->first_and_last_name,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteFuecPassenger.
     */
    public function deleteFuecPassenger(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('FuecPassengerService@deleteFuecPassenger: '.$e->getMessage());
            throw $e;
        }
    }
}
