<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasFiles;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;

/**
 * Modelo Company que representa la tabla companies.
 * Registro maestro de empresas o entidades del sistema
 *
 * @author   Darwin Montes
 *
 * @version  1.0.0
 *
 * @since    1.0.0
 *
 * @created  2026-05-29
 */
class Company extends Model implements HasMedia
{
    use FormatsDates, HasFactory, HasFiles, HasUuid, LogsActivity;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'companies';

    /**
     * Atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'person_type',
        'type_of_company',
        'economic_sector',
        'legal_structure',
        'document_type_uuid',
        'document_number',
        'verification_digit',
        'business_name',
        'trade_name',
        'commercial_registration',
        'municipality_uuid',
        'address',
        'postal_code',
        'phone',
        'email',
        'tax_regime_uuid',
        'currency_code',
        'approximate_number_of_employees',
        'web_page',
        'country_code',
        'legal_representative_name',
        'legal_representative_last_name',
        'legal_representative_document_type',
        'legal_representative_document_number',
        'legal_representative_nationality',
        'legal_representative_document_issue_date',
        'is_active',
    ];

    /**
     * Atributos que deben ser ocultos para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'legal_representative_document_issue_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con TypeOfDocument.
     *
     * @return BelongsTo
     */
    public function documentType()
    {
        return $this->belongsTo(TypeOfDocument::class, 'document_type_uuid', 'uuid');
    }

    /**
     * Relación con TaxRegime.
     *
     * @return BelongsTo
     */
    public function taxRegime()
    {
        return $this->belongsTo(TaxRegime::class, 'tax_regime_uuid', 'uuid');
    }

    /**
     * Relación con City.
     *
     * @return BelongsTo
     */
    public function municipality()
    {
        return $this->belongsTo(City::class, 'municipality_uuid', 'uuid');
    }

    public function taxInformation(): HasOne
    {
        return $this->hasOne(TaxInformation::class, 'company_uuid', 'uuid');
    }

    public function bankDetails(): HasMany
    {
        return $this->hasMany(BankDetail::class, 'company_uuid', 'uuid');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_uuid', 'uuid');
    }

    public function economicActivities(): HasMany
    {
        return $this->hasMany(EconomicActivity::class, 'company_uuid', 'uuid');
    }

    public function enablingResolutions(): HasMany
    {
        return $this->hasMany(EnablingResolution::class, 'company_uuid', 'uuid');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class, 'company_uuid', 'uuid');
    }

    public function financialStatements(): HasMany
    {
        return $this->hasMany(FinancialStatement::class, 'company_uuid', 'uuid');
    }

    public function occupationalSafetyRecords(): HasMany
    {
        return $this->hasMany(OccupationalSafetyRecord::class, 'company_uuid', 'uuid');
    }

    public function rupRecords(): HasMany
    {
        return $this->hasMany(RupRecord::class, 'company_uuid', 'uuid');
    }

    public function taxDeclarations(): HasMany
    {
        return $this->hasMany(TaxDeclaration::class, 'company_uuid', 'uuid');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'company_uuid', 'uuid');
    }

    public function systemConfiguration(): HasOne
    {
        return $this->hasOne(SystemConfiguration::class, 'company_uuid', 'uuid');
    }
}
