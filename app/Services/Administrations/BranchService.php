<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\Branch;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
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
