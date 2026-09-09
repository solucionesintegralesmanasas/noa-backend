<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\Department;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de Departamento.
 *
 * Este servicio se encarga de procesar y persistir la información de los departamentos
 * de Colombia, asegurando la integridad de los códigos DANE y facilitando la
 * organización territorial dentro del sistema.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class DepartmentService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new Department;
    }

    /**
     * Método getAllDepartmentsWithPagination.
     */
    public function getAllDepartmentsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'dane_code', 'name']);
    }

    /**
     * Método getAllDepartments.
     */
    public function getAllDepartments(): Collection
    {
        return $this->all(columns: ['uuid', 'dane_code', 'name']);
    }

    /**
     * Método getDepartmentByUuid.
     */
    public function getDepartmentByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createDepartment.
     */
    public function createDepartment(array $data): Model
    {
        return $this->transaction(fn () => Department::create([
            'dane_code' => $data['dane_code'],
            'name' => $data['name'],
        ]));
    }

    /**
     * Método updateDepartment.
     */
    public function updateDepartment(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var Department $record */
            $record = Department::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'dane_code' => $data['dane_code'] ?? $record->dane_code,
                'name' => $data['name'] ?? $record->name,
            ]);

            return $record;
        });
    }

    /**
     * Método deleteDepartment.
     */
    public function deleteDepartment(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('DepartmentService@deleteDepartment: '.$e->getMessage());
            throw $e;
        }
    }
}
