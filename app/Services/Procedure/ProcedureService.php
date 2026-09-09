<?php

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Models\Procedure;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Tramite.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de Tramite en el dominio del negocio.
 * Se encarga de gestionar el ciclo de vida de los trámites, desde su radicación
 * hasta su finalización, asegurando la integridad de los datos y las relaciones.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ProcedureService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['procedure_code', 'filed_number', 'procedure_type', 'subject'];

    protected function getModelInstance(): Model
    {
        return new Procedure;
    }

    /**
     * Método getAllProceduresWithPagination.
     */
    public function getAllProceduresWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }

    /**
     * Método getAllProcedures.
     */
    public function getAllProcedures(): Collection
    {
        return $this->all();
    }

    /**
     * Método getProcedureByUuid.
     */
    public function getProcedureByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createProcedure.
     */
    public function createProcedure(array $data): Model
    {
        return $this->transaction(fn () => Procedure::create([
            'link_type' => $data['link_type'] ?? null,
            'company_uuid' => $data['company_uuid'] ?? null,
            'third_party_uuid' => $data['third_party_uuid'] ?? null,
            'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
            'procedure_code' => $data['procedure_code'],
            'filed_number' => $data['filed_number'] ?? null,
            'procedure_type' => $data['procedure_type'],
            'date_of_creation' => $data['date_of_creation'],
            'city_uuid' => $data['city_uuid'],
            'subject' => $data['subject'] ?? null,
            'territorial_director_uuid' => $data['territorial_director_uuid'],
            'status' => $data['status'] ?? 'RECIBIDO',
        ]));
    }

    /**
     * Método updateProcedure.
     */
    public function updateProcedure(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'link_type' => $data['link_type'] ?? $record->link_type,
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'third_party_uuid' => $data['third_party_uuid'] ?? $record->third_party_uuid,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'procedure_code' => $data['procedure_code'] ?? $record->procedure_code,
                'filed_number' => $data['filed_number'] ?? $record->filed_number,
                'procedure_type' => $data['procedure_type'] ?? $record->procedure_type,
                'date_of_creation' => $data['date_of_creation'] ?? $record->date_of_creation,
                'city_uuid' => $data['city_uuid'] ?? $record->city_uuid,
                'subject' => $data['subject'] ?? $record->subject,
                'territorial_director_uuid' => $data['territorial_director_uuid'] ?? $record->territorial_director_uuid,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteProcedure.
     */
    public function deleteProcedure(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ProcedureService@deleteProcedure: '.$e->getMessage());
            throw $e;
        }
    }
}
