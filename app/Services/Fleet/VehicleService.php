<?php

declare(strict_types=1);

namespace App\Services\Fleet;

use App\Models\SystemConfiguration;
use App\Models\Vehicle;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/**
 * Servicio de negocio altamente detallado para la gestión integral de Vehiculo.
 *
 * Este servicio asume la responsabilidad exclusiva de procesar, validar internamente
 * y persistir las operaciones de Vehiculo en el dominio del negocio.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2025-05-15
 */
class VehicleService extends BaseService
{
    /**
     * @var array<string>
     */
    protected array $searchableFields = ['vehicle_license_plate'];

    public function __construct(
        private readonly VehicleBranchService $vehicleBranchService
    ) {
        parent::__construct();
    }

    protected function getModelInstance(): Model
    {
        return new Vehicle;
    }

    /**
     * Método getAllVehiclesWithPagination.
     */
    public function getAllVehiclesWithPagination(int $perPage = 15, int $page = 1, string $search = '', ?string $companyUuid = null, ?string $thirdPartyUuid = null): LengthAwarePaginator
    {
        $columns = [
            'uuid',
            'third_party_uuid',
            'type_of_service',
            'company_uuid',
            'vehicle_license_plate',
            'vehicle_class_uuid',
            'brand_uuid',
            'line',
            'model',
            'body_type',
            'is_active',
        ];

        $query = $this->query()->with([
            'brand:uuid,description',
            'vehicle_class:uuid,description',
            'thirdParty:uuid,first_name,last_name,company_name',
            'branch:branches.uuid,branches.name',
        ]);

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('CONDUCTOR')) {
            $driverThirdPartyUuid = request()->attributes->get('current_third_party_uuid')
                ?? $user->companies()->first()?->pivot?->third_party_uuid
                ?? $user->third_party_uuid;

            if ($driverThirdPartyUuid) {
                $allowedUuids = $this->getVehicleUuidsForConductor($driverThirdPartyUuid);
                $query->whereIn('uuid', $allowedUuids);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($thirdPartyUuid) {
            $query->where(function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid)
                    ->orWhereHas('owners', function ($ownerQuery) use ($thirdPartyUuid) {
                        $ownerQuery->where('third_party_uuid', $thirdPartyUuid);
                    });
            });
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

        $paginator = $query->paginate($perPage, $columns, 'page', $page);

        // Optimización de payload in-place
        $paginator->getCollection()->transform(fn($vehicle) => [
            'uuid' => $vehicle->uuid,
            'vehicle_license_plate' => $vehicle->vehicle_license_plate,
            'line' => $vehicle->line,
            'model' => $vehicle->model,
            'type_of_service' => $vehicle->type_of_service,
            'is_active' => (bool) $vehicle->is_active,
            'brand' => $vehicle->brand ? [
                'description' => $vehicle->brand->description,
            ] : null,
            'vehicle_class' => $vehicle->vehicle_class ? [
                'description' => $vehicle->vehicle_class->description,
            ] : null,
            'branch' => $vehicle->branch ? [
                'name' => $vehicle->branch->name,
                'uuid' => $vehicle->branch->uuid,
            ] : null,
            'third_party' => $vehicle->thirdParty ? [
                'company_name' => $vehicle->thirdParty->company_name,
                'first_name' => $vehicle->thirdParty->first_name,
                'last_name' => $vehicle->thirdParty->last_name,
            ] : null,
        ]);

        return $paginator;
    }

    /**
     * Método getAllVehicles.
     */
    public function getAllVehicles(?string $companyUuid = null, ?string $thirdPartyUuid = null): Collection
    {
        $query = $this->query();

        if (! $companyUuid) {
            $companyUuid = request()->attributes->get('current_company_uuid');
        }

        $user = Auth::user();

        // Fallback de empresa si no viene especificada pero el usuario pertenece a una
        if (! $companyUuid && $user) {
            $companyUuid = $user->companies()->first()?->uuid;
        }

        if ($companyUuid) {
            $this->applyCompanyFilter($query, $companyUuid);
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('CONDUCTOR')) {
            $driverThirdPartyUuid = request()->attributes->get('current_third_party_uuid')
                ?? $user->companies()->first()?->pivot?->third_party_uuid
                ?? $user->third_party_uuid;

            if ($driverThirdPartyUuid) {
                $allowedUuids = $this->getVehicleUuidsForConductor($driverThirdPartyUuid);
                $query->whereIn('uuid', $allowedUuids);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($thirdPartyUuid) {
            $query->where(function ($q) use ($thirdPartyUuid) {
                $q->where('third_party_uuid', $thirdPartyUuid)
                    ->orWhereHas('owners', function ($ownerQuery) use ($thirdPartyUuid) {
                        $ownerQuery->where('third_party_uuid', $thirdPartyUuid);
                    });
            });
        }

        return $query->get(['uuid', 'company_uuid', 'vehicle_license_plate', 'vehicle_class_uuid']);
    }

    /**
     * Obtiene los UUIDs de los vehículos asignados estrictamente a un conductor:
     * 1. Vehículos donde esté vinculado directamente en Proyectos existentes
     *    (project_driver_vehicles para este conductor, incluyendo historial ante cambio de vehículo).
     * 2. Vehículos donde exista un extracto de contrato (FUEC) donde figure como conductor.
     *
     * @return array<int, string>
     */
    public function getVehicleUuidsForConductor(string $driverThirdPartyUuid): array
    {
        // 1. Vehículos vinculados directamente a este conductor en Proyectos existentes
        // Incluye tanto la asignación activa como el historial ante cambios de vehículo del conductor
        $projectVehicleUuids = \App\Models\ProjectDriverVehicle::withoutGlobalScopes()
            ->where('third_party_uuid', $driverThirdPartyUuid)
            ->whereHas('project')
            ->pluck('vehicle_uuid')
            ->filter()
            ->unique()
            ->toArray();

        // 2. Vehículos donde el conductor figura en extractos de contrato (FUEC)
        $fuecVehicleUuids = \App\Models\Fuec::withoutGlobalScopes()
            ->where(function ($q) use ($driverThirdPartyUuid) {
                $q->where('main_conductor_uuid', $driverThirdPartyUuid)
                    ->orWhere('secondary_conductor_uuid', $driverThirdPartyUuid)
                    ->orWhere('tertiary_conductor_uuid', $driverThirdPartyUuid);
            })
            ->whereNotNull('vehicle_uuid')
            ->pluck('vehicle_uuid')
            ->filter()
            ->unique()
            ->toArray();

        return array_values(array_unique(array_filter(array_merge(
            $projectVehicleUuids,
            $fuecVehicleUuids
        ))));
    }

    /**
     * Método getVehicleByUuid.
     */
    public function getVehicleByUuid(string $uuid): ?Model
    {
        $vehicle = $this->findByUuid($uuid);
        if ($vehicle) {
            $vehicle->load([
                'owners',
                'vehicleDocuments',
                'operationCards',
                'businessCollaborationAgreements',
                'branch',
                'vehicleBranches',
                'vehicleInspections',
            ]);
        }

        return $vehicle;
    }

    /**
     * Método createVehicle.
     */
    public function createVehicle(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            $internalNumber = $data['internal_number'] ?? null;

            if (empty($internalNumber)) {
                $config = SystemConfiguration::query()->where('company_uuid', $data['company_uuid'])->first();
                if ($config && $config->fuec_enable_auto_internal_number && $config->vehicle_internal_number_counter !== null) {
                    $hasAgreements = false;
                    if (!empty($data['uuid'])) {
                        $hasAgreements = \Illuminate\Support\Facades\DB::table('business_collaboration_agreements')
                            ->where('vehicle_uuid', $data['uuid'])
                            ->exists();
                    }
                    if (!$hasAgreements) {
                        $internalNumber = (string) $config->vehicle_internal_number_counter;
                        $config->increment('vehicle_internal_number_counter', 1, []);
                    }
                }
            }

            $vehicle = Vehicle::create([
                'company_uuid' => $data['company_uuid'],
                'third_party_uuid' => $data['third_party_uuid'] ?? null,
                'vehicle_license_plate' => $data['vehicle_license_plate'],
                'transit_license_number' => $data['transit_license_number'],
                'type_of_service' => $data['type_of_service'] ?? 'PUBLICO',
                'vehicle_class_uuid' => $data['vehicle_class_uuid'],
                'brand_uuid' => $data['brand_uuid'],
                'line' => $data['line'],
                'model' => $data['model'],
                'color' => $data['color'],
                'serial_number' => $data['serial_number'] ?? null,
                'engine_number' => $data['engine_number'],
                'chassis_number' => $data['chassis_number'],
                'vin_number' => $data['vin_number'] ?? null,
                'engine_displacement' => $data['engine_displacement'],
                'body_type' => $data['body_type'],
                'fuel_type' => $data['fuel_type'],
                'registration_date' => $data['registration_date'],
                'transit_authority' => $data['transit_authority'],
                'doors' => $data['doors'],
                'load_capacity' => $data['load_capacity'],
                'gross_vehicle_weight' => $data['gross_vehicle_weight'],
                'passenger_capacity' => $data['passenger_capacity'],
                'seated_passenger_capacity' => $data['seated_passenger_capacity'],
                'number_of_axles' => $data['number_of_axles'],
                'exact_payment' => $data['exact_payment'] ?? 0,
                'internal_number' => $internalNumber,
                'address_type' => $data['address_type'] ?? 'N/A',
                'steering_type' => $data['steering_type'] ?? null,
                'transmission_type' => $data['transmission_type'] ?? null,
                'number_of_speeds' => $data['number_of_speeds'] ?? null,
                'bearing_type' => $data['bearing_type'] ?? null,
                'rear_suspension' => $data['rear_suspension'] ?? null,
                'number_of_tires' => $data['number_of_tires'] ?? null,
                'rim_size' => $data['rim_size'] ?? null,
                'rim_material' => $data['rim_material'] ?? null,
                'front_brake_type' => $data['front_brake_type'] ?? null,
                'rear_brake_type' => $data['rear_brake_type'] ?? null,
                'number_of_windows' => $data['number_of_windows'] ?? null,
                'is_active' => $data['is_active'] ?? 1,
            ]);

            if (isset($data['owner'])) {
                $vehicle->owners()->create([
                    'third_party_uuid' => $data['owner']['third_party_uuid'] ?? null,
                    'document_type_uuid' => $data['owner']['document_type_uuid'],
                    'owner_name' => $data['owner']['owner_name'],
                    'document_number' => $data['owner']['document_number'],
                    'verification_digit' => $data['owner']['verification_digit'] ?? null,
                ]);
            }

            if (! empty($data['branch_uuid'])) {
                $this->vehicleBranchService->createVehicleBranch([
                    'company_uuid' => $vehicle->company_uuid,
                    'vehicle_uuid' => $vehicle->uuid,
                    'branch_uuid' => $data['branch_uuid'],
                    'entry_date' => now()->toDateString(),
                    'exit_type' => $data['exit_type'] ?? 'ENTRADA',
                ]);
            }

            return $vehicle;
        });
    }

    /**
     * Método updateVehicle.
     */
    public function updateVehicle(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            $internalNumber = $data['internal_number'] ?? $record->internal_number;
            if (empty($internalNumber)) {
                $config = SystemConfiguration::query()->where('company_uuid', $record->company_uuid)->first();
                if ($config && $config->fuec_enable_auto_internal_number && $config->vehicle_internal_number_counter !== null) {
                    $hasAgreements = \Illuminate\Support\Facades\DB::table('business_collaboration_agreements')
                        ->where('vehicle_uuid', $record->uuid)
                        ->exists();

                    if (!$hasAgreements) {
                        $internalNumber = (string) $config->vehicle_internal_number_counter;
                        $config->increment('vehicle_internal_number_counter', 1, []);
                    }
                }
            }

            $record->update([
                'third_party_uuid' => array_key_exists('third_party_uuid', $data) ? $data['third_party_uuid'] : $record->third_party_uuid,
                'vehicle_license_plate' => $data['vehicle_license_plate'] ?? $record->vehicle_license_plate,
                'transit_license_number' => $data['transit_license_number'] ?? $record->transit_license_number,
                'type_of_service' => $data['type_of_service'] ?? $record->type_of_service,
                'vehicle_class_uuid' => $data['vehicle_class_uuid'] ?? $record->vehicle_class_uuid,
                'brand_uuid' => $data['brand_uuid'] ?? $record->brand_uuid,
                'line' => $data['line'] ?? $record->line,
                'model' => $data['model'] ?? $record->model,
                'color' => $data['color'] ?? $record->color,
                'serial_number' => array_key_exists('serial_number', $data) ? $data['serial_number'] : $record->serial_number,
                'engine_number' => $data['engine_number'] ?? $record->engine_number,
                'chassis_number' => $data['chassis_number'] ?? $record->chassis_number,
                'vin_number' => array_key_exists('vin_number', $data) ? $data['vin_number'] : $record->vin_number,
                'engine_displacement' => $data['engine_displacement'] ?? $record->engine_displacement,
                'body_type' => $data['body_type'] ?? $record->body_type,
                'fuel_type' => $data['fuel_type'] ?? $record->fuel_type,
                'registration_date' => $data['registration_date'] ?? $record->registration_date,
                'transit_authority' => $data['transit_authority'] ?? $record->transit_authority,
                'doors' => $data['doors'] ?? $record->doors,
                'load_capacity' => $data['load_capacity'] ?? $record->load_capacity,
                'gross_vehicle_weight' => $data['gross_vehicle_weight'] ?? $record->gross_vehicle_weight,
                'passenger_capacity' => $data['passenger_capacity'] ?? $record->passenger_capacity,
                'seated_passenger_capacity' => $data['seated_passenger_capacity'] ?? $record->seated_passenger_capacity,
                'number_of_axles' => $data['number_of_axles'] ?? $record->number_of_axles,
                'exact_payment' => array_key_exists('exact_payment', $data) ? (bool) $data['exact_payment'] : $record->exact_payment,
                'internal_number' => $internalNumber,
                'address_type' => $data['address_type'] ?? $record->address_type,
                'steering_type' => $data['steering_type'] ?? $record->steering_type,
                'transmission_type' => $data['transmission_type'] ?? $record->transmission_type,
                'number_of_speeds' => $data['number_of_speeds'] ?? $record->number_of_speeds,
                'bearing_type' => $data['bearing_type'] ?? $record->bearing_type,
                'rear_suspension' => $data['rear_suspension'] ?? $record->rear_suspension,
                'number_of_tires' => $data['number_of_tires'] ?? $record->number_of_tires,
                'rim_size' => $data['rim_size'] ?? $record->rim_size,
                'rim_material' => $data['rim_material'] ?? $record->rim_material,
                'front_brake_type' => $data['front_brake_type'] ?? $record->front_brake_type,
                'rear_brake_type' => $data['rear_brake_type'] ?? $record->rear_brake_type,
                'number_of_windows' => $data['number_of_windows'] ?? $record->number_of_windows,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $record->is_active,
            ]);

            if (isset($data['owner'])) {
                $record->owners()->updateOrCreate(
                    ['vehicle_uuid' => $record->uuid],
                    [
                        'third_party_uuid' => $data['owner']['third_party_uuid'] ?? null,
                        'document_type_uuid' => $data['owner']['document_type_uuid'],
                        'owner_name' => $data['owner']['owner_name'],
                        'document_number' => $data['owner']['document_number'],
                        'verification_digit' => $data['owner']['verification_digit'] ?? null,
                    ]
                );
            }

            if (array_key_exists('branch_uuid', $data)) {
                if (! empty($data['branch_uuid'])) {
                    $currentActive = $this->vehicleBranchService->query()
                        ->where('vehicle_uuid', $record->uuid)
                        ->where('is_active', 1)
                        ->first();

                    if ($currentActive) {
                        if ($currentActive->branch_uuid !== $data['branch_uuid']) {
                            $this->vehicleBranchService->updateVehicleBranch($currentActive->uuid, [
                                'is_active' => 0,
                                'exit_date' => now()->toDateString(),
                                'exit_type' => 'SALIDA',
                            ]);

                            $this->vehicleBranchService->createVehicleBranch([
                                'company_uuid' => $record->company_uuid,
                                'vehicle_uuid' => $record->uuid,
                                'branch_uuid' => $data['branch_uuid'],
                                'entry_date' => now()->toDateString(),
                                'exit_date' => now()->toDateString(),
                                'exit_type' => 'ENTRADA',
                                'is_active' => 1,
                            ]);
                        }
                    } else {
                        $this->vehicleBranchService->createVehicleBranch([
                            'company_uuid' => $record->company_uuid,
                            'vehicle_uuid' => $record->uuid,
                            'branch_uuid' => $data['branch_uuid'],
                            'entry_date' => $data['entry_date'] ?? $record->registration_date ?? now()->toDateString(),
                            'exit_date' => $data['exit_date'] ?? $data['registration_date'] ?? now()->toDateString(),
                            'exit_type' => 'ENTRADA',
                            'is_active' => 1,
                        ]);
                    }
                } else {
                    $activeBranches = $this->vehicleBranchService->query()
                        ->where('vehicle_uuid', $record->uuid)
                        ->where('is_active', 1)
                        ->get();

                    foreach ($activeBranches as $vb) {
                        $this->vehicleBranchService->updateVehicleBranch($vb->uuid, [
                            'is_active' => 0,
                            'exit_date' => now()->toDateString(),
                            'exit_type' => 'SALIDA',
                        ]);
                    }
                }
            }

            return $record->fresh();
        });
    }

    /**
     * Método deleteVehicle.
     */
    public function deleteVehicle(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('VehicleService@deleteVehicle: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Método toggleVehicleStatus.
     */
    public function toggleVehicleStatus(string $uuid): Model
    {
        return $this->transaction(function () use ($uuid) {
            $record = $this->findByUuid($uuid);
            $record->update([
                'is_active' => ! $record->is_active,
            ]);

            return $record->fresh();
        });
    }

    /**
     * Método getVehicleProfile.
     *
     *
     * @return array<string, mixed>
     */
    public function getVehicleProfile(string $uuid): array
    {
        $columns = [
            'uuid',
            'company_uuid',
            'third_party_uuid',
            'vehicle_license_plate',
            'transit_license_number',
            'type_of_service',
            'vehicle_class_uuid',
            'brand_uuid',
            'line',
            'model',
            'color',
            'serial_number',
            'engine_number',
            'chassis_number',
            'vin_number',
            'engine_displacement',
            'body_type',
            'fuel_type',
            'registration_date',
            'transit_authority',
            'doors',
            'load_capacity',
            'gross_vehicle_weight',
            'passenger_capacity',
            'seated_passenger_capacity',
            'number_of_axles',
            'exact_payment',
            'is_active',
            'internal_number',
        ];

        $relations = [
            'third_party:uuid,first_name,last_name,document_number,document_type_uuid,company_name,trade_name',
            'third_party.type_of_document:uuid,prefix',
            'brand:uuid,description',
            'business_collaboration_agreements:uuid,vehicle_uuid,expiry_date,contracting_entity_name,agreement_internal_id',
            'vehicle_class:uuid,description',
            'operation_cards:vehicle_uuid,uuid,affiliated_company,area_of_coverage,service_type,transport_mode,issue_date,expiration_date,operating_card_number',
            'vehicle_documents:vehicle_uuid,uuid,document_type,policy_number,issue_date,effective_date,expiry_date,issuing_entity,tariff_code,status',
            'affiliate_admin_charges:vehicle_uuid,next_payment_date,payment_date,status',
            'maintenances:uuid,vehicle_uuid,maintenance_type,next_maintenance_date,status',
            'branch:branches.uuid,branches.name',
        ];

        $record = $this->findByUuid($uuid, $columns, $relations);
        if (! $record) {
            throw new ModelNotFoundException("Vehicle with UUID {$uuid} not found.");
        }

        return [
            'uuid' => $record->uuid,
            'vehicle_license_plate' => $record->vehicle_license_plate,
            'transit_license_number' => $record->transit_license_number,
            'line' => $record->line,
            'model' => $record->model,
            'color' => $record->color,
            'serial_number' => $record->serial_number,
            'engine_number' => $record->engine_number,
            'chassis_number' => $record->chassis_number,
            'vin_number' => $record->vin_number,
            'engine_displacement' => $record->engine_displacement,
            'body_type' => $record->body_type,
            'doors' => $record->doors,
            'registration_date' => $record->registration_date,
            'transit_authority' => $record->transit_authority,
            'internal_number' => $record->internal_number,
            'exact_payment' => (bool) $record->exact_payment,
            'is_active' => (bool) $record->is_active,
            'type_of_service' => $record->type_of_service,
            'fuel_type' => $record->fuel_type,
            'number_of_axles' => $record->number_of_axles,
            'load_capacity' => $record->load_capacity,
            'gross_vehicle_weight' => $record->gross_vehicle_weight,
            'seated_passenger_capacity' => $record->seated_passenger_capacity,
            'brand_uuid' => $record->brand_uuid,
            'vehicle_class_uuid' => $record->vehicle_class_uuid,
            'company_uuid' => $record->company_uuid,
            'branch_uuid' => $record->branch_uuid,
            'third_party_uuid' => $record->third_party_uuid,
            'brand' => $record->brand ? [
                'uuid' => $record->brand->uuid,
                'description' => $record->brand->description,
            ] : null,
            'vehicle_class' => $record->vehicle_class ? [
                'uuid' => $record->vehicle_class->uuid,
                'description' => $record->vehicle_class->description,
            ] : null,
            'branch' => $record->branch ? [
                'uuid' => $record->branch->uuid,
                'name' => $record->branch->name,
            ] : null,
            'third_party' => $record->third_party ? [
                'uuid' => $record->third_party->uuid,
                'company_name' => $record->third_party->company_name,
                'trade_name' => $record->third_party->trade_name,
                'first_name' => $record->third_party->first_name,
                'last_name' => $record->third_party->last_name,
                'document_number' => $record->third_party->document_number,
                'document_type_uuid' => $record->third_party->document_type_uuid,
                'type_of_document' => $record->third_party->type_of_document ? [
                    'uuid' => $record->third_party->type_of_document->uuid,
                    'prefix' => $record->third_party->type_of_document->prefix,
                ] : null,
            ] : null,
            'business_collaboration_agreements' => $record->business_collaboration_agreements ? $record->business_collaboration_agreements->map(fn($bca) => [
                'uuid' => $bca->uuid,
                'agreement_internal_id' => $bca->agreement_internal_id,
                'contracting_entity_name' => $bca->contracting_entity_name,
                'expiry_date' => $bca->expiry_date,
            ])->toArray() : [],
            'operation_cards' => $record->operation_cards ? $record->operation_cards->map(fn($oc) => [
                'uuid' => $oc->uuid,
                'affiliated_company' => $oc->affiliated_company,
                'area_of_coverage' => $oc->area_of_coverage,
                'service_type' => $oc->service_type,
                'transport_mode' => $oc->transport_mode,
                'operating_card_number' => $oc->operating_card_number,
                'expiration_date' => $oc->expiration_date,
            ])->toArray() : [],
            'vehicle_documents' => $record->vehicle_documents ? $record->vehicle_documents->map(fn($vd) => [
                'uuid' => $vd->uuid,
                'document_type' => $vd->document_type,
                'policy_number' => $vd->policy_number,
                'issuing_entity' => $vd->issuing_entity,
                'tariff_code' => $vd->tariff_code,
                'issue_date' => $vd->issue_date,
                'effective_date' => $vd->effective_date,
                'expiry_date' => $vd->expiry_date,
                'status' => $vd->status,
            ])->toArray() : [],
            'affiliate_admin_charges' => $record->affiliate_admin_charges ? $record->affiliate_admin_charges->map(fn($aac) => [
                'uuid' => $aac->uuid,
                'next_payment_date' => $aac->next_payment_date,
                'payment_date' => $aac->payment_date,
                'status' => $aac->status,
            ])->toArray() : [],
            'maintenances' => $record->maintenances ? $record->maintenances->map(fn($m) => [
                'uuid' => $m->uuid,
                'maintenance_type' => $m->maintenance_type,
                'next_maintenance_date' => $m->next_maintenance_date,
                'status' => $m->status,
            ])->toArray() : [],
        ];
    }

    /**
     * Método getTechnicalSheetData.
     */
    public function getTechnicalSheetData(string $uuid): Vehicle
    {
        $columns = [
            'uuid',
            'company_uuid',
            'third_party_uuid',
            'vehicle_license_plate',
            'transit_license_number',
            'type_of_service',
            'vehicle_class_uuid',
            'brand_uuid',
            'line',
            'model',
            'color',
            'serial_number',
            'engine_number',
            'chassis_number',
            'vin_number',
            'engine_displacement',
            'body_type',
            'fuel_type',
            'registration_date',
            'transit_authority',
            'doors',
            'load_capacity',
            'gross_vehicle_weight',
            'passenger_capacity',
            'seated_passenger_capacity',
            'number_of_axles',
            'exact_payment',
            'is_active',
            'internal_number',
        ];

        $relations = [
            'company:uuid,business_name,document_number',
            'third_party:uuid,first_name,last_name,company_name,document_number,document_type_uuid,company_uuid,address,phone,municipality_uuid',
            'third_party.type_of_document:uuid,prefix',
            'third_party.municipality:uuid,name',
            'brand:uuid,description',
            'business_collaboration_agreements:uuid,vehicle_uuid,expiry_date,contracting_entity_name,agreement_internal_id',
            'vehicle_class:uuid,description',
            'operation_cards:vehicle_uuid,uuid,affiliated_company,area_of_coverage,service_type,transport_mode,issue_date,expiration_date,operating_card_number,status',
            'vehicle_documents:vehicle_uuid,uuid,document_type,policy_number,issue_date,effective_date,expiry_date,issuing_entity,tariff_code,status',
            'affiliate_admin_charges:vehicle_uuid,next_payment_date,payment_date,status',
            'maintenances:uuid,vehicle_uuid,maintenance_type,next_maintenance_date,status',
            'branch:branches.uuid,branches.name',
        ];

        $record = $this->findByUuid($uuid, $columns, $relations);
        if (! $record) {
            throw new ModelNotFoundException("Vehicle with UUID {$uuid} not found.");
        }

        return $record;
    }

    /**
     * Método getHandoverRecordData.
     *
     * Obtiene los datos del vehículo y de su propietario/tercero para
     * alimentar la plantilla de "Acta de Entrega" (handover-record).
     * El PDF se imprime y su contenido restante se diligencia a mano.
     *
     * @return array<string, mixed>
     */
    public function getHandoverRecordData(string $uuid): array
    {
        $columns = [
            'uuid',
            'company_uuid',
            'third_party_uuid',
            'vehicle_license_plate',
            'transit_license_number',
            'type_of_service',
            'vehicle_class_uuid',
            'brand_uuid',
            'line',
            'model',
            'color',
            'chassis_number',
            'vin_number',
            'engine_number',
            'engine_displacement',
            'body_type',
            'fuel_type',
            'registration_date',
            'transit_authority',
            'seated_passenger_capacity',
            'internal_number',
            'is_active',
        ];

        $relations = [
            'company:uuid,business_name,document_number',
            'third_party:uuid,first_name,last_name,company_name,trade_name,document_number,document_type_uuid,address,phone,municipality_uuid',
            'third_party.type_of_document:uuid,prefix',
            'third_party.municipality:uuid,name',
            'brand:uuid,description',
            'vehicle_class:uuid,description',
            'owners',
            'owners.documentType:uuid,prefix',
            'vehicle_documents:vehicle_uuid,uuid,document_type,policy_number,status,expiry_date',
        ];

        $record = $this->findByUuid($uuid, $columns, $relations);
        if (! $record) {
            throw new ModelNotFoundException("Vehicle with UUID {$uuid} not found.");
        }

        $owner = $record->owners->first();
        $thirdParty = $record->third_party;

        // Resolución del nombre del propietario (persona natural, jurídica o dueño registrado)
        if ($thirdParty && ($thirdParty->first_name || $thirdParty->last_name)) {
            $ownerName = trim(($thirdParty->first_name ?? '').' '.($thirdParty->last_name ?? ''));
        } elseif ($thirdParty && ($thirdParty->company_name || $thirdParty->trade_name)) {
            $ownerName = trim(($thirdParty->company_name ?? '').' '.($thirdParty->trade_name ?? ''));
        } elseif ($owner && $owner->owner_name) {
            $ownerName = $owner->owner_name;
        } else {
            $ownerName = '';
        }

        // Resolución de la identificación del propietario
        $ownerDoc = '';
        if ($thirdParty && $thirdParty->document_number) {
            $ownerDoc = ($thirdParty->type_of_document && $thirdParty->type_of_document->prefix
                ? $thirdParty->type_of_document->prefix.' '
                : '').$thirdParty->document_number;
        } elseif ($owner && $owner->document_number) {
            $ownerDoc = ($owner->documentType && $owner->documentType->prefix
                ? $owner->documentType->prefix.' '
                : '').$owner->document_number
                .($owner->verification_digit ? '-'.$owner->verification_digit : '');
        }

        // Resolución del número de tecnomecánica (RTM) vigente del vehículo.
        // El sistema marca el documento como vigente con status "SI" o "VIGENTE".
        $tecno = '';
        if ($record->vehicle_documents && $record->vehicle_documents->isNotEmpty()) {
            $rtm = $record->vehicle_documents
                ->filter(function ($doc) {
                    $isRtm = strtoupper($doc->document_type) === 'RTM';
                    $isVigente = in_array($doc->status, ['SI', 'VIGENTE'], true);
                    $noVencido = ! $doc->expiry_date || $doc->expiry_date->isFuture();

                    return $isRtm && $isVigente && $noVencido;
                })
                ->sortByDesc('expiry_date')
                ->first();
            $tecno = $rtm?->policy_number ?? '';
        }

        return [
            'vehiculo' => [
                'placa' => $record->vehicle_license_plate ?? '',
                'marca' => $record->brand->description ?? '',
                'vehiculo' => $record->line ?? '',
                'chasis' => $record->chassis_number ?? $record->vin_number ?? '',
                'combustible' => $record->fuel_type ?? '',
                'carroceria' => $record->body_type ?? '',
                'modelo' => $record->model ?? '',
                'licencia' => $record->transit_license_number ?? '',
                'clase_servicio' => $record->type_of_service ?? '',
                'cilindraje' => $record->engine_displacement
                    ? $record->engine_displacement.' cc'
                    : '',
                'motor' => $record->engine_number ?? '',
                'tecno' => $tecno,
            ],
            'propietario' => [
                'nombre' => $ownerName,
                'cedula' => $ownerDoc,
                'lugar' => $thirdParty && $thirdParty->municipality
                    ? $thirdParty->municipality->name
                    : '',
            ],
            'entrega' => [
                'fecha' => '',
                'cargo' => '',
                'nombre_entrega' => '',
                'delegado' => '',
            ],
            'company' => $record->company ? [
                'uuid' => $record->company->uuid,
                'business_name' => $record->company->business_name,
                'document_number' => $record->company->document_number,
            ] : null,
            'company_uuid' => $record->company_uuid,
        ];
    }

    /**
     * Método getVehicleHistory.
     */
    public function getVehicleHistory(string $uuid): array
    {
        $vehicle = $this->findByUuid($uuid);
        if (! $vehicle) {
            throw new ModelNotFoundException("Vehicle with UUID {$uuid} not found.");
        }

        return $vehicle->getHistory();
    }
}
