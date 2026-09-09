<?php

declare(strict_types=1);

namespace App\Services\HumanResources;

use App\Models\EmploymentContract;
use App\Models\ThirdParty;
use App\Services\BaseService;
use Exception;

class EmploymentContractService extends BaseService
{
    /**
     * Devuelve una instancia del modelo.
     */
    protected function getModelInstance(): \Illuminate\Database\Eloquent\Model
    {
        return new EmploymentContract;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'thirdParty:uuid,trade_name,first_name,last_name,document_number',
    ];

    /**
     * Listado paginado con relaciones y búsqueda por nombre/documento del empleado.
     */
    public function getPaginatedData(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        array $relations = [],
        array $columns = ['*']
    ): \Illuminate\Pagination\LengthAwarePaginator {
        $query = $this->query()
            ->with(self::RELATIONS)
            ->join('third_parties', 'third_parties.uuid', '=', 'employment_contracts.third_party_uuid')
            ->select('employment_contracts.*');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('third_parties.first_name', 'like', "%{$search}%")
                  ->orWhere('third_parties.last_name',  'like', "%{$search}%")
                  ->orWhere('third_parties.trade_name', 'like', "%{$search}%")
                  ->orWhere('third_parties.document_number', 'like', "%{$search}%")
                  ->orWhere('employment_contracts.contract_type', 'like', "%{$search}%")
                  ->orWhere('employment_contracts.status', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['employment_contracts.*'], 'page', $page);
    }

    /**
     * Create a new employment contract.
     *
     * @param array $data
     * @return EmploymentContract
     * @throws Exception
     */
    public function create(array $data): EmploymentContract
    {
        // Verify third party is an employee
        $thirdParty = ThirdParty::where('uuid', $data['third_party_uuid'])->firstOrFail();
        if (!$thirdParty->is_employee) {
            throw new Exception('El tercero especificado no está marcado como empleado.');
        }

        // Set the company UUID automatically from the authenticated user's context (handled by trait or explicitly here)
        if (!isset($data['company_uuid'])) {
            $data['company_uuid'] = auth()->user()->current_company_uuid ?? $thirdParty->company_uuid;
        }

        return parent::create($data);
    }

    /**
     * Update an existing employment contract.
     * Applies business validation before delegating to the base update.
     *
     * @param string $uuid
     * @param array  $data
     * @return EmploymentContract
     * @throws Exception
     */
    public function updateContract(string $uuid, array $data): EmploymentContract
    {
        if (isset($data['status']) && $data['status'] === 'TERMINADO' && empty($data['termination_reason'])) {
            throw new Exception('Debe especificar el motivo de terminación.');
        }

        parent::update($uuid, $data);

        return $this->findByUuid($uuid);
    }
}
