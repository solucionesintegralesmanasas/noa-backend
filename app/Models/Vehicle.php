<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Descripción funcional de la clase Vehicle.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class Vehicle extends Model
{
    use BelongsToCompany;
    use FormatsDates;
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'vehicles';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
        'branch_uuid',
        'exact_payment',
        'internal_number',
        'address_type',
        'steering_type',
        'transmission_type',
        'number_of_speeds',
        'bearing_type',
        'rear_suspension',
        'number_of_tires',
        'rim_size',
        'rim_material',
        'front_brake_type',
        'rear_brake_type',
        'number_of_windows',
        'is_active',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'registration_date' => 'date',
        'doors' => 'integer',
        'load_capacity' => 'integer',
        'gross_vehicle_weight' => 'integer',
        'passenger_capacity' => 'integer',
        'seated_passenger_capacity' => 'integer',
        'number_of_axles' => 'integer',
        'exact_payment' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con brands.
     *
     * @return BelongsTo
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_uuid', 'uuid');
    }

    /**
     * Relación con vehicle_class.
     *
     * @return BelongsTo
     */
    public function vehicleClass()
    {
        return $this->belongsTo(VehicleClass::class, 'vehicle_class_uuid', 'uuid');
    }

    /**
     * Relación con vehicle_class (alias snake_case).
     *
     * @return BelongsTo
     */
    public function vehicle_class()
    {
        return $this->vehicleClass();
    }

    /**
     * Relación con branches a través de la tabla intermedia vehicles_branches.
     *
     * @return HasOneThrough
     */
    public function branch()
    {
        return $this->hasOneThrough(
            Branch::class,
            VehicleBranch::class,
            'vehicle_uuid',
            'uuid',
            'uuid',
            'branch_uuid'
        )->where('vehicles_branches.is_active', 1);
    }

    /**
     * Relación con companies.
     *
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Relación con third_parties.
     *
     * @return BelongsTo
     */
    public function thirdParty()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Relación con owners.
     *
     * @return HasMany
     */
    public function owners()
    {
        return $this->hasMany(Owner::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con vehicle_documents.
     *
     * @return HasMany
     */
    public function vehicleDocuments()
    {
        return $this->hasMany(VehicleDocument::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con operation_cards.
     *
     * @return HasMany
     */
    public function operationCards()
    {
        return $this->hasMany(OperationCard::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con operation_cards (alias snake_case).
     *
     * @return HasMany
     */
    public function operation_cards()
    {
        return $this->operationCards();
    }

    /**
     * Relación con business_collaboration_agreements.
     *
     * @return HasMany
     */
    public function businessCollaborationAgreements()
    {
        return $this->hasMany(BusinessCollaborationAgreement::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con business_collaboration_agreements (alias snake_case).
     *
     * @return HasMany
     */
    public function business_collaboration_agreements()
    {
        return $this->businessCollaborationAgreements();
    }

    /**
     * Relación con maintenance.
     *
     * @return HasMany
     */
    public function maintenance()
    {
        return $this->hasMany(Maintenance::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con vehicle_inspections.
     *
     * @return HasMany
     */
    public function vehicleInspections()
    {
        return $this->hasMany(VehicleInspection::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con vehicle_branches.
     *
     * @return HasMany
     */
    public function vehicleBranches()
    {
        return $this->hasMany(VehicleBranch::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con third_parties (alias snake_case).
     *
     * @return BelongsTo
     */
    public function third_party()
    {
        return $this->thirdParty();
    }

    /**
     * Relación con vehicle_documents (alias snake_case).
     *
     * @return HasMany
     */
    public function vehicle_documents()
    {
        return $this->vehicleDocuments();
    }

    /**
     * Relación con maintenances (alias snake_case).
     *
     * @return HasMany
     */
    public function maintenances()
    {
        return $this->maintenance();
    }

    /**
     * Relación con affiliate_admin_charges.
     *
     * @return HasMany
     */
    public function affiliateAdminCharges()
    {
        return $this->hasMany(AffiliateAdminCharge::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con los proyectos a los que pertenece el vehículo.
     *
     * Cada fila del pivote relaciona el vehículo con el conductor que lo usa
     * dentro de un proyecto (relación 1:1).
     *
     * @return BelongsToMany
     */
    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_driver_vehicles',
            'vehicle_uuid',
            'project_uuid',
            'uuid',
            'uuid'
        )->withPivot('uuid', 'third_party_uuid')->withTimestamps();
    }

    /**
     * Relación con las asignaciones pivote vehículo-proyecto-conductor.
     *
     * @return HasMany
     */
    public function driverVehicleAssignments()
    {
        return $this->hasMany(ProjectDriverVehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Relación con affiliate_admin_charges (alias snake_case).
     *
     * @return HasMany
     */
    public function affiliate_admin_charges()
    {
        return $this->affiliateAdminCharges();
    }

    /**
     * Obtiene el historial completo del vehículo con todas sus relaciones.
     *
     * @return array<string, mixed>
     */
    public function getHistory(): array
    {
        $this->load([
            'brand:uuid,description',
            'vehicleClass:uuid,description',
            'company:uuid,business_name,document_number',
            'thirdParty:uuid,first_name,last_name,company_name,document_number,document_type_uuid,company_uuid',
            'thirdParty.type_of_document:uuid,prefix',
            'branch:branches.uuid,branches.name',
            'owners',
            'vehicleDocuments',
            'operationCards',
            'businessCollaborationAgreements',
            'maintenance',
            'vehicleInspections',
            'vehicleBranches',
            'affiliateAdminCharges',
            'activities',
        ]);

        return [
            'vehicle' => $this->toArray(),
            'brand' => $this->brand,
            'vehicle_class' => $this->vehicleClass,
            'company' => $this->company,
            'third_party' => $this->thirdParty,
            'branch' => $this->branch,
            'owners' => $this->owners,
            'documents' => $this->vehicleDocuments,
            'operation_cards' => $this->operationCards,
            'business_collaboration_agreements' => $this->businessCollaborationAgreements,
            'maintenances' => $this->maintenance,
            'inspections' => $this->vehicleInspections,
            'vehicle_branches' => $this->vehicleBranches,
            'affiliate_admin_charges' => $this->affiliateAdminCharges,
            'activity_log' => $this->activities,
        ];
    }
}
