<?php

declare(strict_types=1);

namespace App\Services\ServiceDeliveryControlSheet;

use App\Models\ServiceDeliveryControlSheet;
use App\Models\Signature;
use App\Services\BaseService;
use App\Services\Signature\SignatureService;
use App\Utils\Logger;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para la gestión de hojas de control de entrega de servicios.
 *
 * @author   Darwin Montes
 *
 * @version  V 1.0.0
 *
 * @since    V 1.0.0
 *
 * @created  2026-06-19
 */
class ServiceDeliveryControlSheetService extends BaseService
{
    public function __construct(
        private readonly ServiceInternalControlService $serviceInternalControlService,
        private readonly ServiceInternalControlSubcontractedService $serviceInternalControlSubcontractedService,
        private readonly SignatureService $signatureService
    ) {
        parent::__construct();
    }

    protected array $searchableFields = [
        'official_name_and_surname',
        'daily_route',
    ];

    protected function getModelInstance(): Model
    {
        return new ServiceDeliveryControlSheet;
    }

    public function getAllServiceDeliveryControlSheetsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        $query = $this->query()
            ->whereNull('parent_uuid')
            ->withCount(['children as children_total'])
            ->withCount(['children as children_open' => function ($q) {
                $q->where('is_active', true);
            }]);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if (! empty($search) && ! empty($this->searchableFields)) {
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($item) {
            $item->setAttribute('control_status', $this->resolveControlStatus($item));

            return $item;
        });

        return $paginator;
    }

    public function getAllServiceDeliveryControlSheets(): Collection
    {
        return $this->query()
            ->whereNull('parent_uuid')
            ->with('children')
            ->get();
    }

    /**
     * Calcula el estado de control de una hoja de control de servicio.
     *
     * Para servicios multi-día el estado considera las planillas diarias:
     * - ABIERTA: todas las planillas diarias están abiertas.
     * - PARCIAL: algunas planillas diarias están abiertas y otras cerradas.
     * - CERRADA: todas las planillas diarias (y el servicio) están cerradas.
     */
    private function resolveControlStatus(Model $record): string
    {
        $total = (int) ($record->children_total ?? 0);
        $open = (int) ($record->children_open ?? 0);

        if ($total > 0) {
            if ($open === 0) {
                return 'CERRADA';
            }

            if ($open < $total) {
                return 'PARCIAL';
            }

            return 'ABIERTA';
        }

        return $record->is_active ? 'ABIERTA' : 'CERRADA';
    }

    public function getServiceDeliveryControlSheetByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }

    public function createServiceDeliveryControlSheet(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $record = ServiceDeliveryControlSheet::create([
                'official_name_and_surname' => $data['official_name_and_surname'] ?? null,
                'service_date' => $data['start_date'] ?? $data['service_date'],
                'start_date' => $data['start_date'] ?? $data['service_date'],
                'end_date' => $data['end_date'] ?? null,
                'daily_route' => $data['daily_route'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'total_hours' => $data['total_hours'] ?? null,
                'starting_kilometer' => $data['starting_kilometer'] ?? null,
                'ending_kilometer' => $data['ending_kilometer'] ?? null,
                'number_of_tolls' => $data['number_of_tolls'] ?? null,
                'total_toll_value' => $data['total_toll_value'] ?? null,
                'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
                'is_active' => $data['is_active'] ?? true,
                'company_uuid' => $data['company_uuid'],
            ]);

            $this->serviceInternalControlService->createServiceInternalControl([
                'company_uuid' => $data['company_uuid'],
                'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'fuec_uuid' => $data['fuec_uuid'] ?? null,
                'service_delivery_control_sheet_uuid' => $record->uuid,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Si es un servicio multi-día (end_date > start_date), generar los registros diarios hijos
            $startDate = Carbon::parse($data['start_date'] ?? $data['service_date']);
            $endDate = $data['end_date'] ? Carbon::parse($data['end_date']) : null;

            if ($endDate && $endDate->greaterThan($startDate)) {
                $currentDate = $startDate->copy()->addDay();

                while (! $currentDate->greaterThan($endDate)) {
                    $child = ServiceDeliveryControlSheet::create([
                        'official_name_and_surname' => $data['official_name_and_surname'] ?? null,
                        'service_date' => $currentDate->toDateString(),
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString(),
                        'parent_uuid' => $record->uuid,
                        'daily_route' => $data['daily_route'] ?? null,
                        'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
                        'is_active' => true,
                        'company_uuid' => $data['company_uuid'],
                    ]);

                    $this->serviceInternalControlService->createServiceInternalControl([
                        'company_uuid' => $data['company_uuid'],
                        'vehicle_uuid' => $data['vehicle_uuid'] ?? null,
                        'third_party_uuid' => $data['third_party_uuid'] ?? null,
                        'fuec_uuid' => $data['fuec_uuid'] ?? null,
                        'service_delivery_control_sheet_uuid' => $child->uuid,
                        'is_active' => false,
                    ]);

                    $currentDate->addDay();
                }
            }

            return $record->fresh();
        });
    }

    public function updateServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'official_name_and_surname' => $data['official_name_and_surname'] ?? $record->official_name_and_surname,
                'service_date' => $data['service_date'] ?? $record->service_date,
                'daily_route' => $data['daily_route'] ?? $record->daily_route,
                'start_time' => $data['start_time'] ?? $record->start_time,
                'end_time' => $data['end_time'] ?? $record->end_time,
                'total_hours' => $data['total_hours'] ?? $record->total_hours,
                'starting_kilometer' => $data['starting_kilometer'] ?? $record->starting_kilometer,
                'ending_kilometer' => $data['ending_kilometer'] ?? $record->ending_kilometer,
                'number_of_tolls' => $data['number_of_tolls'] ?? $record->number_of_tolls,
                'total_toll_value' => $data['total_toll_value'] ?? $record->total_toll_value,
                'type_of_control_sheet' => 'DIRECTO_CON_LA_EMPRESA',
                'is_active' => $data['is_active'] ?? $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    public function deleteServiceDeliveryControlSheet(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('ServiceDeliveryControlSheetService@deleteServiceDeliveryControlSheet: '.$e->getMessage());
            throw $e;
        }
    }

    public function startServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'start_time' => $data['start_time'] ?? $record->start_time,
                'starting_kilometer' => $data['starting_kilometer'] ?? $record->starting_kilometer,
            ]);

            if (! empty($data['fuec_uuid'])) {
                if ($record->type_of_control_sheet === 'DIRECTO_CON_LA_EMPRESA' && $record->internalControl) {
                    $this->serviceInternalControlService->updateServiceInternalControl($record->internalControl->uuid, [
                        'fuec_uuid' => $data['fuec_uuid'],
                    ]);
                }
            }

            return $record->fresh();
        });
    }

    public function closeServiceDeliveryControlSheet(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            // Si es un servicio multi-día (padre), no permitir cerrarlo mientras
            // queden planillas diarias abiertas.
            $pendingChildren = ServiceDeliveryControlSheet::query()
                ->where('parent_uuid', $record->uuid)
                ->where('is_active', true)
                ->count();

            if ($pendingChildren > 0) {
                throw ValidationException::withMessages([
                    'close' => "No se puede cerrar el servicio hasta completar todas las planillas diarias ({$pendingChildren} pendiente(s)).",
                ]);
            }

            $record->update([
                'end_time' => $data['end_time'] ?? $record->end_time,
                'ending_kilometer' => $data['ending_kilometer'] ?? $record->ending_kilometer,
                'total_hours' => $data['total_hours'] ?? $record->total_hours,
                'number_of_tolls' => $data['number_of_tolls'] ?? $record->number_of_tolls,
                'total_toll_value' => $data['total_toll_value'] ?? $record->total_toll_value,
                'is_active' => false,
            ]);

            // Guardar firmas del funcionario y conductor en el registro (planilla diaria).
            if (! empty($data['funcionario_signature'])) {
                $this->signatureService->store([
                    'signature' => $data['funcionario_signature'],
                    'entity_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                    'entity_id' => $record->id,
                    'company_uuid' => $record->company_uuid,
                ]);
            }

            if (! empty($data['conductor_signature'])) {
                $this->signatureService->store([
                    'signature' => $data['conductor_signature'],
                    'entity_type' => 'App\\Models\\ServiceDeliveryControlSheet',
                    'entity_id' => $record->id,
                    'company_uuid' => $record->company_uuid,
                ]);
            }

            // Si es una planilla diaria (hijo), cerrar el servicio padre cuando
            // sea la última planilla pendiente.
            if (! empty($record->parent_uuid)) {
                $remainingChildren = ServiceDeliveryControlSheet::query()
                    ->where('parent_uuid', $record->parent_uuid)
                    ->where('is_active', true)
                    ->count();

                if ($remainingChildren === 0) {
                    ServiceDeliveryControlSheet::query()
                        ->where('uuid', $record->parent_uuid)
                        ->update(['is_active' => false]);
                }
            }

            return $record->fresh();
        });
    }
}
