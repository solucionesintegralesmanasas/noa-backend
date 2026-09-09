<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\EnablingResolution;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Resoluciones de Habilitación Electrónica.
 *
 * Administra las resoluciones oficiales que autorizan a las empresas a emitir
 * documentos electrónicos, gestionando números de resolución, números FUEC
 * y códigos territoriales, asegurando la vigencia legal de la operación.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class EnablingResolutionService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['resolution_number'];

    protected function getModelInstance(): Model
    {
        return new EnablingResolution;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllEnablingResolutionsWithPagination.
     */
    public function getAllEnablingResolutionsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        $paginator = $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn ($er) => [
            'uuid' => $er->uuid,
            'resolution_number' => $er->resolution_number,
            'number_fuec' => $er->number_fuec,
            'territorial_code' => $er->territorial_code,
            'resolution_date' => $er->resolution_date,
            'status' => $er->status,
            'company' => $er->company ? [
                'business_name' => $er->company->business_name,
            ] : null,
        ]);

        return $paginator;
    }

    /**
     * Método getAllEnablingResolutions.
     */
    public function getAllEnablingResolutions(?string $companyUuid = null): Collection
    {
        $query = $this->query()->with(self::RELATIONS);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        return $query->get();
    }

    /**
     * Método getEnablingResolutionByUuid.
     */
    public function getEnablingResolutionByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createEnablingResolution.
     */
    public function createEnablingResolution(array $data): Model
    {
        return $this->transaction(fn () => EnablingResolution::create([
            'company_uuid' => $data['company_uuid'],
            'resolution_number' => $data['resolution_number'],
            'number_fuec' => $data['number_fuec'],
            'territorial_code' => $data['territorial_code'],
            'resolution_date' => $data['resolution_date'],
            'status' => $data['status'] ?? 1,
        ]));
    }

    /**
     * Método updateEnablingResolution.
     */
    public function updateEnablingResolution(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'resolution_number' => $data['resolution_number'] ?? $record->resolution_number,
                'number_fuec' => $data['number_fuec'] ?? $record->number_fuec,
                'territorial_code' => $data['territorial_code'] ?? $record->territorial_code,
                'resolution_date' => $data['resolution_date'] ?? $record->resolution_date,
                'status' => $data['status'] ?? $record->status,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteEnablingResolution.
     */
    public function deleteEnablingResolution(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }

    /**
     * Método toggleEnablingResolutionStatus.
     */
    public function toggleEnablingResolutionStatus(string $uuid): Model
    {
        try {
            $this->toggleStatus($uuid, 'status');

            return $this->findByUuid($uuid, relations: self::RELATIONS);
        } catch (\Exception $e) {
            Logger::error('EnablingResolutionService@toggleEnablingResolutionStatus: '.$e->getMessage());
            throw $e;
        }
    }
}
