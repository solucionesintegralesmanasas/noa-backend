<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @author Darwin Montes
 *
 * @version 1.0.0
 *
 * @created_at 2026-06-13
 *
 * @module Settings
 *
 * @resource SystemConfiguration
 */
class SystemConfiguration extends Model implements HasMedia
{
    use BelongsToCompany, HasFactory, HasUuid, InteractsWithMedia, LogsActivity;

    protected $table = 'system_configuration';

    /**
     * La relación de archivos no se serializa (SPEC-006): cada archivo calcula su URL cargando el modelo dueño,
     * que vuelve a incluir sus archivos, y con instancias nuevas el guard antirrecursión no la frena.
     * Lo que consumen el frontend y los PDFs son las tres URLs de `toArray()`.
     */
    protected $hidden = ['media'];

    protected $fillable = [
        'uuid',
        'company_uuid',
        'fuec_require_daily_inspections',
        'fuec_require_social_security',
        'maintenance_alert_days',
        'maintenance_numbers_days',
        'maintenance_alert_km',
        'payment_cutoff_day',
        'document_alert_days',
        'license_alert_days',
        'operation_card_alert_days',
        'soat_alert_days',
        'rtm_alert_days',
        'activate_notifications',
        'notify_by_email',
        'notification_email',
        'own_company_names',
        'fuec_pdf_show_signatures',
        'fuec_pdf_show_contractor_details',
        'fuec_pdf_show_route_details',
        'pdf_branding',
        'vehicle_internal_number_counter',
        'fuec_enable_auto_internal_number',
        'fuec_use_corporate_policies',
        'corporate_rcc_insurer',
        'corporate_rcc_expiration',
        'corporate_rce_expiration',
        'default_territorial_director_uuid',
        'default_territorial_director_name',
        'rcc_insurer_company',
        'rce_insurer_company',
        'rcc_policy_number',
        'rce_policy_number',
        'platform_fee_type',
        'platform_fee_rates',
    ];

    protected $casts = [
        'fuec_require_daily_inspections' => 'boolean',
        'fuec_require_social_security' => 'boolean',
        'maintenance_alert_days' => 'integer',
        'maintenance_alert_km' => 'integer',
        'payment_cutoff_day' => 'integer',
        'document_alert_days' => 'integer',
        'license_alert_days' => 'integer',
        'operation_card_alert_days' => 'integer',
        'soat_alert_days' => 'integer',
        'rtm_alert_days' => 'integer',
        'activate_notifications' => 'boolean',
        'notify_by_email' => 'boolean',
        'own_company_names' => 'array',
        'fuec_pdf_show_signatures' => 'boolean',
        'fuec_pdf_show_contractor_details' => 'boolean',
        'fuec_pdf_show_route_details' => 'boolean',
        'pdf_branding' => 'array',
        'vehicle_internal_number_counter' => 'integer',
        'fuec_enable_auto_internal_number' => 'boolean',
        'fuec_use_corporate_policies' => 'boolean',
        'corporate_rce_expiration' => 'date:Y-m-d',
        'platform_fee_rates' => 'array',
    ];

    // ============================================================================
    // SPATIE MEDIALIBRARY - Colecciones de imágenes
    // ============================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('MINISTRY_LOGO')->singleFile();
        $this->addMediaCollection('SUPER_LOGO')->singleFile();
        $this->addMediaCollection('LETTERHEAD')->singleFile();
    }

    // ============================================================================
    // ACCESORES RÁPIDOS
    // ============================================================================

    public function getMinistryLogoAttribute(): ?Media
    {
        return $this->getFirstMedia('MINISTRY_LOGO');
    }

    public function getMinistryLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('MINISTRY_LOGO');
    }

    public function getSuperLogoAttribute(): ?Media
    {
        return $this->getFirstMedia('SUPER_LOGO');
    }

    public function getSuperLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('SUPER_LOGO');
    }

    public function getLetterheadAttribute(): ?Media
    {
        return $this->getFirstMedia('LETTERHEAD');
    }

    public function getLetterheadUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('LETTERHEAD');
    }

    // ============================================================================
    // MÉTODO addFile (similar al trait HasFiles)
    // ============================================================================

    /**
     * Agrega un archivo a una colección de Spatie MediaLibrary.
     */
    public function addFile($file, string $collectionName, array $customProperties = []): Media
    {
        return $this->addMedia($file)
            ->withCustomProperties($customProperties)
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection($collectionName);
    }

    // ============================================================================
    // SERIALIZACIÓN
    // ============================================================================

    public function toArray(): array
    {
        $array = parent::toArray();

        // Inyectar URLs de media sin recursión
        $array['ministry_logo_url'] = $this->urlSegura('ministry_logo_url');
        $array['super_logo_url'] = $this->urlSegura('super_logo_url');
        $array['letterhead_url'] = $this->urlSegura('letterhead_url');

        return $array;
    }

    /** Un adjunto roto (archivo faltante, colección vacía) degrada a URL vacía y nunca tumba la petición. */
    private function urlSegura(string $accesor): string
    {
        try {
            return (string) $this->{$accesor};
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
