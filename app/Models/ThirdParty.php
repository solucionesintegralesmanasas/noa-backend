<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\FormatsDates;
use App\Traits\HasFiles;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * Registro maestro de terceros (clientes, proveedores, empleados) con datos tributarios DIAN.
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class ThirdParty extends Model implements HasMedia
{
    use BelongsToCompany, FormatsDates, HasFactory, HasFiles, HasUuid, LogsActivity;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'third_parties';

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'person_type',
        'document_type_uuid',
        'document_number',
        'nit_check_digit',
        'trade_name',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'municipality_uuid',
        'tax_regime',
        'tax_responsibility_uuid',
        'bank_account_number',
        'bank_account_type',
        'bank_name',
        'cost_center_uuid',
        'is_customer',
        'is_supplier',
        'is_employee',
        'is_affiliate',
        'is_driver',
        'is_others',
        'is_active',
    ];

    /**
     * Los atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Los atributos que deben ser casteados.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_customer' => 'boolean',
        'is_supplier' => 'boolean',
        'is_employee' => 'boolean',
        'is_affiliate' => 'boolean',
        'is_driver' => 'boolean',
        'is_others' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Obtiene la empresa propietaria del tercero.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Obtiene el tipo de documento del tercero.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(TypeOfDocument::class, 'document_type_uuid', 'uuid');
    }

    /**
     * Obtiene el municipio del tercero.
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(City::class, 'municipality_uuid', 'uuid');
    }

    /**
     * Obtiene la responsabilidad fiscal del tercero.
     */
    public function taxResponsibility(): BelongsTo
    {
        return $this->belongsTo(TaxResponsibility::class, 'tax_responsibility_uuid', 'uuid');
    }

    /**
     * Obtiene las licencias de conducción del tercero.
     */
    public function driverLicenses()
    {
        return $this->hasMany(DriverLicense::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Obtiene las licencias de conducción del tercero (alias snake_case).
     */
    public function driver_licenses()
    {
        return $this->driverLicenses();
    }

    /**
     * Obtiene los proyectos a los que pertenece el tercero (conductor).
     *
     * El pivote incluye el vehículo asignado a cada conductor en el proyecto.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            Project::class,
            'project_driver_vehicles',
            'third_party_uuid',
            'project_uuid',
            'uuid',
            'uuid'
        )->withPivot('uuid', 'vehicle_uuid')->withTimestamps();
    }

    /**
     * Obtiene las asignaciones pivote conductor-vehículo por proyecto.
     */
    public function driverVehicleAssignments(): HasMany
    {
        return $this->hasMany(ProjectDriverVehicle::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Obtiene los aportes de seguridad social (PILA) del tercero.
     */
    public function socialSecurityContributions()
    {
        return $this->hasMany(SocialSecurityContribution::class, 'third_party_uuid', 'uuid');
    }

    /**
     * Obtiene el tipo de documento del tercero (alias snake_case).
     */
    public function type_of_document()
    {
        return $this->documentType();
    }
}
