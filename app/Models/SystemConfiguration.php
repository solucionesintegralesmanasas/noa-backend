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
        'fuec_pdf_show_signatures',
        'fuec_pdf_show_contractor_details',
        'fuec_pdf_show_route_details',
        'vehicle_internal_number_counter',
        'fuec_enable_auto_internal_number',
        'fuec_use_corporate_policies',
        'corporate_rcc_insurer',
        'corporate_rcc_expiration',
        'corporate_rce_expiration',
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
        'fuec_pdf_show_signatures' => 'boolean',
        'fuec_pdf_show_contractor_details' => 'boolean',
        'fuec_pdf_show_route_details' => 'boolean',
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
        $array['ministry_logo_url'] = $this->ministry_logo_url;
        $array['super_logo_url'] = $this->super_logo_url;
        $array['letterhead_url'] = $this->letterhead_url;

        return $array;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
