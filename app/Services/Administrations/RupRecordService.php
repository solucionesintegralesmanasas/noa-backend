<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\RupRecord;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión del Registro Único de Proponentes (RUP).
 *
 * Administra las inscripciones de las empresas en el RUP, gestionando sus
 * calificaciones de capacidad jurídica, financiera, organizacional y de contratación,
 * además de controlar la vigencia y expedición de certificados oficiales.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class RupRecordService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['registration_number'];

    protected function getModelInstance(): Model
    {
        return new RupRecord;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllRupRecordsWithPagination.
     */
    public function getAllRupRecordsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllRupRecords.
     */
    public function getAllRupRecords(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getRupRecordByUuid.
     */
    public function getRupRecordByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createRupRecord.
     */
    public function createRupRecord(array $data): Model
    {
        return $this->transaction(fn () => RupRecord::create([
            'company_uuid' => $data['company_uuid'],
            'registration_number' => $data['registration_number'],
            'issue_date' => $data['issue_date'] ?? null,
            'expiration_date' => $data['expiration_date'] ?? null,
            'legal_capacity_score' => $data['legal_capacity_score'] ?? null,
            'financial_capacity_score' => $data['financial_capacity_score'] ?? null,
            'organizational_capacity_score' => $data['organizational_capacity_score'] ?? null,
            'contracting_capacity_score' => $data['contracting_capacity_score'] ?? null,
            'rup_certificate_path' => $data['rup_certificate_path'] ?? null,
            'status' => $data['status'] ?? 'VIGENTE',
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    /**
     * Método updateRupRecord.
     */
    public function updateRupRecord(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'registration_number' => $data['registration_number'] ?? $record->registration_number,
                'issue_date' => $data['issue_date'] ?? $record->issue_date,
                'expiration_date' => $data['expiration_date'] ?? $record->expiration_date,
                'legal_capacity_score' => $data['legal_capacity_score'] ?? $record->legal_capacity_score,
                'financial_capacity_score' => $data['financial_capacity_score'] ?? $record->financial_capacity_score,
                'organizational_capacity_score' => $data['organizational_capacity_score'] ?? $record->organizational_capacity_score,
                'contracting_capacity_score' => $data['contracting_capacity_score'] ?? $record->contracting_capacity_score,
                'rup_certificate_path' => $data['rup_certificate_path'] ?? $record->rup_certificate_path,
                'status' => $data['status'] ?? $record->status,
                'remarks' => $data['remarks'] ?? $record->remarks,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteRupRecord.
     */
    public function deleteRupRecord(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }

    /**
     * Método toggleRupRecordStatus.
     */
    public function toggleRupRecordStatus(string $uuid): Model
    {
        try {
            // Mapa de transición de estados para el ENUM
            $this->toggleStatus($uuid, 'status', [
                'VIGENTE' => 'SUSPENDIDO',
                'SUSPENDIDO' => 'VIGENTE',
                'VENCIDO' => 'RENOVADO',
                'RENOVADO' => 'VENCIDO',
                // Puedes agregar más transiciones si las necesitas
            ]);

            return $this->findByUuid($uuid, relations: self::RELATIONS);
        } catch (\Exception $e) {
            Logger::error('RupRecordService@toggleRupRecordStatus: '.$e->getMessage());
            throw $e;
        }
    }
}
