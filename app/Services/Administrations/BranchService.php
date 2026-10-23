<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\Branch;
use App\Models\Company;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión operativa de Sucursales y Sedes.
 *
 * Facilita la administración de los puntos físicos de operación de las empresas,
 * permitiendo definir sedes principales y sucursales secundarias, manteniendo
 * siempre la georreferenciación con los municipios del sistema.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class BranchService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    public function __construct()
    {
        parent::__construct();
        $this->sortableFields = [
            'name' => 'branches.name',
            'company' => fn (Builder $query, string $direction) => $query->orderBy(
                Company::query()
                    ->select('business_name')
                    ->whereColumn('companies.uuid', 'branches.company_uuid')
                    ->take(1),
                $direction
            ),
            'status' => 'branches.status',
        ];
    }

    protected function getModelInstance(): Model
    {
        return new Branch;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
        'municipality:uuid,name',
    ];

    /**
     * Método getAllBranchesWithPagination.
     */
    public function getAllBranchesWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null,
        ?string $sortBy = null,
        string $sortOrder = 'asc'
    ): LengthAwarePaginator {
        return $this->getPaginatedData(
            perPage: $perPage,
            page: $page,
            search: $search,
            companyUuid: $companyUuid,
            relations: self::RELATIONS,
            sortBy: $sortBy,
            sortOrder: $sortOrder
        );
    }

    /**
     * Método getAllBranches.
     */
    public function getAllBranches(?string $companyUuid = null): Collection
    {
        $query = $this->query()->with(self::RELATIONS);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        return $query->get();
    }

    /**
     * Método getBranchByUuid.
     */
    public function getBranchByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createBranch.
     */
    public function createBranch(array $data): Model
    {
        return $this->transaction(fn () => Branch::create([
            'company_uuid' => $data['company_uuid'],
            'name' => $data['name'],
            'address' => $data['address'],
            'municipality_uuid' => $data['municipality_uuid'],
            'is_primary' => $data['is_primary'] ?? false,
            'status' => $data['status'] ?? 1,
        ]));
    }

    /**
     * Método updateBranch.
     */
    public function updateBranch(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'name' => $data['name'] ?? $record->name,
                'address' => $data['address'] ?? $record->address,
                'municipality_uuid' => $data['municipality_uuid'] ?? $record->municipality_uuid,
                'is_primary' => $data['is_primary'] ?? $record->is_primary,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteBranch.
     */
    public function deleteBranch(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }

    /**
     * Método toggleBranchStatus.
     */
    public function toggleBranchStatus(string $uuid): Model
    {
        try {
            $this->toggleStatus($uuid, 'status');

            return $this->findByUuid($uuid, relations: self::RELATIONS);
        } catch (\Exception $e) {
            Logger::error('BranchService@toggleBranchStatus: '.$e->getMessage());
            throw $e;
        }
    }
}
