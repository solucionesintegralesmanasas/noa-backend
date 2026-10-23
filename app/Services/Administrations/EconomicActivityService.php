<?php

declare(strict_types=1);

namespace App\Services\Administrations;

use App\Models\Company;
use App\Models\EconomicActivity;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio de negocio para la gestión de Actividades Económicas (CIIU).
 *
 * Administra el catálogo de actividades económicas registradas por cada empresa,
 * permitiendo identificar la actividad principal y las secundarias según la
 * clasificación industrial internacional uniforme adaptada para Colombia.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class EconomicActivityService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['activity_code'];

    public function __construct()
    {
        parent::__construct();
        $this->sortableFields = [
            'activity_code' => 'economic_activities.activity_code',
            'activity_description' => 'economic_activities.activity_description',
            'company_uuid' => fn (Builder $query, string $direction) => $query->orderBy(
                Company::query()
                    ->select('business_name')
                    ->whereColumn('companies.uuid', 'economic_activities.company_uuid')
                    ->take(1),
                $direction
            ),
            'company' => fn (Builder $query, string $direction) => $query->orderBy(
                Company::query()
                    ->select('business_name')
                    ->whereColumn('companies.uuid', 'economic_activities.company_uuid')
                    ->take(1),
                $direction
            ),
            'is_main_activity' => 'economic_activities.is_main_activity',
        ];
    }

    protected function getModelInstance(): Model
    {
        return new EconomicActivity;
    }

    /** @var array<int, string> Relaciones estándar cargadas en consultas de detalle y catálogo. */
    private const RELATIONS = [
        'company:uuid,business_name',
    ];

    /**
     * Método getAllEconomicActivitiesWithPagination.
     */
    public function getAllEconomicActivitiesWithPagination(
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
     * Método getAllEconomicActivities.
     */
    public function getAllEconomicActivities(): Collection
    {
        return $this->all(relations: self::RELATIONS);
    }

    /**
     * Método getEconomicActivityByUuid.
     */
    public function getEconomicActivityByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid, relations: self::RELATIONS);
    }

    /**
     * Método createEconomicActivity.
     */
    public function createEconomicActivity(array $data): Model
    {
        return $this->transaction(fn () => EconomicActivity::create([
            'company_uuid' => $data['company_uuid'],
            'activity_code' => $data['activity_code'],
            'activity_description' => $data['activity_description'] ?? null,
            'is_main_activity' => $data['is_main_activity'] ?? false,
        ]));
    }

    /**
     * Método updateEconomicActivity.
     */
    public function updateEconomicActivity(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $record->update([
                'company_uuid' => $data['company_uuid'] ?? $record->company_uuid,
                'activity_code' => $data['activity_code'] ?? $record->activity_code,
                'activity_description' => $data['activity_description'] ?? $record->activity_description,
                'is_main_activity' => $data['is_main_activity'] ?? $record->is_main_activity,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método deleteEconomicActivity.
     */
    public function deleteEconomicActivity(string $uuid): void
    {
        if (! $this->delete($uuid)) {
            throw new \RuntimeException("No se pudo eliminar el registro con identificador: {$uuid}");
        }
    }
}
