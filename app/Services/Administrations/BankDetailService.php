<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\BankDetail;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Información Bancaria Corporativa.
 *
 * Administra los detalles de las cuentas bancarias de las empresas, permitiendo
 * registrar el banco, tipo de cuenta, número y titular, asegurando que la información
 * financiera esté disponible para procesos de pago y tesorería.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class BankDetailService extends BaseService
{
    protected function getModelInstance(): Model
    {
        return new BankDetail;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getBankDetailByUuid.
     */
    public function getBankDetailByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * @var array<string>
     */
    protected array $searchableFields = ['bank_name'];

    /**
     * Método getAllBankDetailsWithPagination.
     */
    public function getAllBankDetailsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllBankDetails.
     */
    public function getAllBankDetails(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método createBankDetail.
     */
    public function createBankDetail(array $data): Model
    {
        return $this->transaction(fn () => BankDetail::create([
            'company_uuid' => $data['company_uuid'],
            'bank_name' => $data['bank_name'] ?? null,
            'branch_office' => $data['branch_office'] ?? null,
            'account_type' => $data['account_type'] ?? null,
            'account_number' => $data['account_number'],
            'account_holder' => $data['account_holder'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]));
    }

    /**
     * Método updateBankDetail.
     */
    public function updateBankDetail(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'bank_name' => $data['bank_name'] ?? $record->bank_name,
                'branch_office' => $data['branch_office'] ?? $record->branch_office,
                'account_type' => $data['account_type'] ?? $record->account_type,
                'account_number' => $data['account_number'] ?? $record->account_number,
                'account_holder' => $data['account_holder'] ?? $record->account_holder,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteBankDetail.
     */
    public function deleteBankDetail(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }

    /**
     * Método toggleBankDetailStatus.
     */
    public function toggleBankDetailStatus(string $uuid): Model
    {
        $this->toggleStatus($uuid, 'is_active');

        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }
}
