<?php

declare(strict_types=1);

namespace App\Services\ContractExtraction;

use App\Models\Contractor;
use App\Models\Fuec;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Contratistas.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de Contratistas en el dominio del negocio.
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
class ContractorService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['company_name', 'document_number'];

    protected function getModelInstance(): Model
    {
        return new Contractor;
    }

    /**
     * Método getAllContractorsWithPagination.
     */
    public function getAllContractorsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $vehicleUuid = null
    ): LengthAwarePaginator {
        $query = $this->query();

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($vehicleUuid) {
            $query->where('vehicle_uuid', $vehicleUuid);
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

        return $this->conObjetoDeContrato($query->paginate($perPage, ['*'], 'page', $page));
    }

    /**
     * Adjunta a cada contratista de la página el objeto del contrato de su último FUEC.
     *
     * La pantalla de registro de FUEC precarga los datos al digitar el número de
     * documento y el objeto del contrato vive en `fuecs`, no en `contractors`:
     * una consulta de máximo por contratista + una de los objetos, sin N+1.
     */
    private function conObjetoDeContrato(LengthAwarePaginator $contratistas): LengthAwarePaginator
    {
        $uuids = $contratistas->getCollection()->pluck('uuid')->all();

        if ($uuids === []) {
            return $contratistas;
        }

        $ultimoFuec = DB::table('fuec')
            ->selectRaw('contractor_uuid, MAX(id) as id')
            ->whereIn('contractor_uuid', $uuids)
            ->groupBy('contractor_uuid')
            ->pluck('id', 'contractor_uuid');

        if ($ultimoFuec->isEmpty()) {
            return $contratistas;
        }

        $objetos = DB::table('fuec')
            ->whereIn('id', $ultimoFuec->values())
            ->pluck('object_contract_uuid', 'contractor_uuid');

        $contratistas->getCollection()->each(function (Contractor $contratante) use ($objetos): void {
            $contratante->object_contract_uuid = $objetos[$contratante->uuid] ?? null;
        });

        return $contratistas;
    }

    /**
     * Método getAllContractors.
     */
    public function getAllContractors(?string $companyUuid = null): Collection
    {
        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        $query = $this->query();

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        return $query->get();
    }

    /**
     * Método getContractorByUuid.
     */
    public function getContractorByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createContractor.
     */
    public function createContractor(array $data): Model
    {
        return $this->transaction(fn () => Contractor::create([
            'company_uuid' => $data['company_uuid'],
            'document_type_uuid' => $data['document_type_uuid'],
            'document_number' => $data['document_number'],
            'company_name' => $data['company_name'],
            'address' => $data['address'],
            'telephone' => $data['telephone'],
            'contract_number' => $data['contract_number'],
            'contracting_party_city' => $data['contracting_party_city'],
            'vehicle_uuid' => $data['vehicle_uuid'],
            'responsible_name' => $data['responsible_name'],
            'responsible_document' => $data['responsible_document'],
            'responsible_phone' => $data['responsible_phone'],
            'responsible_address' => $data['responsible_address'],
            'status' => $data['status'] ?? 1,
        ]));
    }

    /**
     * Método updateContractor.
     */
    public function updateContractor(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var Contractor $record */
            $record = Contractor::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'document_type_uuid' => $data['document_type_uuid'] ?? $record->document_type_uuid,
                'document_number' => $data['document_number'] ?? $record->document_number,
                'company_name' => $data['company_name'] ?? $record->company_name,
                'address' => $data['address'] ?? $record->address,
                'telephone' => $data['telephone'] ?? $record->telephone,
                'contract_number' => $data['contract_number'] ?? $record->contract_number,
                'contracting_party_city' => $data['contracting_party_city'] ?? $record->contracting_party_city,
                'vehicle_uuid' => $data['vehicle_uuid'] ?? $record->vehicle_uuid,
                'responsible_name' => $data['responsible_name'] ?? $record->responsible_name,
                'responsible_document' => $data['responsible_document'] ?? $record->responsible_document,
                'responsible_phone' => $data['responsible_phone'] ?? $record->responsible_phone,
                'responsible_address' => $data['responsible_address'] ?? $record->responsible_address,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteContractor.
     */
    public function deleteContractor(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ContractorService@deleteContractor: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Método toggleContractorStatus.
     */
    public function toggleContractorStatus(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            /** @var Contractor $record */
            $record = Contractor::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'status' => $record->status === 1 ? 0 : 1,
            ]);

            return $record;
        });
    }
}
