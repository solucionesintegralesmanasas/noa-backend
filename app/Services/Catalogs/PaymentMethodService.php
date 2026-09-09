<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use App\Models\PaymentMethod;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio detallado para la gestión integral de MedioPago.
 *
 * Este servicio administra los métodos de pago aceptados para la facturación
 * electrónica, asegurando que cada transacción reporte el código DIAN correcto.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-29
 */
class PaymentMethodService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['name'];

    protected function getModelInstance(): Model
    {
        return new PaymentMethod;
    }

    /**
     * Método getAllPaymentMethodsWithPagination.
     */
    public function getAllPaymentMethodsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, [], ['uuid', 'dian_code', 'name']);
    }

    /**
     * Método getAllPaymentMethods.
     */
    public function getAllPaymentMethods(): Collection
    {
        return $this->all(columns: ['uuid', 'dian_code', 'name']);
    }

    /**
     * Método getPaymentMethodByUuid.
     */
    public function getPaymentMethodByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    /**
     * Método createPaymentMethod.
     */
    public function createPaymentMethod(array $data): Model
    {
        return $this->transaction(fn () => PaymentMethod::create([
            'dian_code' => $data['dian_code'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }

    /**
     * Método updatePaymentMethod.
     */
    public function updatePaymentMethod(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            /** @var PaymentMethod $record */
            $record = PaymentMethod::query()->where('uuid', $uuid)->firstOrFail();
            $record->update([
                'dian_code' => $data['dian_code'] ?? $record->dian_code,
                'name' => $data['name'] ?? $record->name,
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record;
        });
    }

    /**
     * Método deletePaymentMethod.
     */
    public function deletePaymentMethod(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('PaymentMethodService@deletePaymentMethod: '.$e->getMessage());
            throw $e;
        }
    }
}
