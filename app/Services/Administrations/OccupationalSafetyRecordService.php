<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\OccupationalSafetyRecord;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Registros de Seguridad y Salud en el Trabajo (SST).
 *
 * Administra los indicadores de gestión SST de las empresas, incluyendo la siniestralidad
 * laboral (fatalidades, accidentes, incidentes), días de incapacidad y cumplimiento
 * del SG-SST según la normatividad vigente y certificaciones ARL.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class OccupationalSafetyRecordService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['year'];

    protected function getModelInstance(): Model
    {
        return new OccupationalSafetyRecord;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllOccupationalSafetyRecordsWithPagination.
     */
    public function getAllOccupationalSafetyRecordsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid, self::RELATIONS);
    }

    /**
     * Método getAllOccupationalSafetyRecords.
     */
    public function getAllOccupationalSafetyRecords(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getOccupationalSafetyRecordByUuid.
     */
    public function getOccupationalSafetyRecordByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createOccupationalSafetyRecord.
     */
    public function createOccupationalSafetyRecord(array $data): Model
    {
        return $this->transaction(fn () => OccupationalSafetyRecord::create([
            'company_uuid' => $data['company_uuid'],
            'operation_year' => $data['operation_year'],
            'fatalities' => $data['fatalities'] ?? 0,
            'incapacitating_accidents_count' => $data['incapacitating_accidents_count'] ?? 0,
            'total_incidents_count' => $data['total_incidents_count'] ?? 0,
            'lost_days_count' => $data['lost_days_count'] ?? 0,
            'average_workers_count' => $data['average_workers_count'] ?? 0,
            'hours_worked' => $data['hours_worked'] ?? null,
            'arl_accident_certificate_date' => $data['arl_accident_certificate_date'] ?? null,
            'risk_level' => $data['risk_level'] ?? null,
            'arl_affiliation_certificate_date' => $data['arl_affiliation_certificate_date'] ?? null,
            'sgsst_rating' => $data['sgsst_rating'] ?? null,
            'sgsst_evaluation_date' => $data['sgsst_evaluation_date'] ?? null,
            'sgsst_certificate_path' => $data['sgsst_certificate_path'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]));
    }

    /**
     * Método updateOccupationalSafetyRecord.
     */
    public function updateOccupationalSafetyRecord(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'operation_year' => $data['operation_year'] ?? $record->operation_year,
                'fatalities' => $data['fatalities'] ?? $record->fatalities,
                'incapacitating_accidents_count' => $data['incapacitating_accidents_count'] ?? $record->incapacitating_accidents_count,
                'total_incidents_count' => $data['total_incidents_count'] ?? $record->total_incidents_count,
                'lost_days_count' => $data['lost_days_count'] ?? $record->lost_days_count,
                'average_workers_count' => $data['average_workers_count'] ?? $record->average_workers_count,
                'hours_worked' => $data['hours_worked'] ?? $record->hours_worked,
                'arl_accident_certificate_date' => $data['arl_accident_certificate_date'] ?? $record->arl_accident_certificate_date,
                'risk_level' => $data['risk_level'] ?? $record->risk_level,
                'arl_affiliation_certificate_date' => $data['arl_affiliation_certificate_date'] ?? $record->arl_affiliation_certificate_date,
                'sgsst_rating' => $data['sgsst_rating'] ?? $record->sgsst_rating,
                'sgsst_evaluation_date' => $data['sgsst_evaluation_date'] ?? $record->sgsst_evaluation_date,
                'sgsst_certificate_path' => $data['sgsst_certificate_path'] ?? $record->sgsst_certificate_path,
                'remarks' => $data['remarks'] ?? $record->remarks,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteOccupationalSafetyRecord.
     */
    public function deleteOccupationalSafetyRecord(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }
}
