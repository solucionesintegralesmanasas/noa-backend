<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\SystemConfiguration;
use App\Services\BaseService;
use App\Utils\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;


/**
 * Servicio de lógica de negocio para configuración del sistema.
 *
 * @author   Darwin Montes
 * @version  V 1.0.0
 * @since    V 1.0.0
 * @created  2025-07-09
 */
class SystemConfigurationService extends BaseService
{
    protected array $searchableFields = [
        'notification_email',
        'corporate_rcc_insurer',
    ];


    protected function getModelInstance(): Model
    {
        return new SystemConfiguration();
    }

    /**
     * Obtiene la configuración de una empresa por su UUID.
     */
    public function getByCompanyUuid(string $companyUuid): ?Model
    {
        return $this->model->query()
            ->with('media')
            ->where('company_uuid', $companyUuid)
            ->first();
    }

    public function getAllSystemConfigurationsWithPagination(
        int $perPage = 15,
        int $page = 1,
        string $search = '',
        ?string $companyUuid = null
    ): LengthAwarePaginator {
        return $this->getPaginatedData($perPage, $page, $search, $companyUuid);
    }


    public function getAllSystemConfigurations(): Collection
    {
        return $this->all();
    }


    public function getSystemConfigurationByUuid(string $uuid): ?Model
    {
        return $this->findByUuid($uuid);
    }


    public function createSystemConfiguration(array $data): Model
    {
        return $this->transaction(function () use ($data) {
            return SystemConfiguration::create([
                'uuid' => $data['uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                'company_uuid' => $data['company_uuid'],
                'fuec_require_daily_inspections' => $data['fuec_require_daily_inspections'] ?? false,
                'fuec_require_social_security' => $data['fuec_require_social_security'] ?? false,
                'maintenance_alert_days' => $data['maintenance_alert_days'] ?? null,
                'maintenance_numbers_days' => $data['maintenance_numbers_days'] ?? null,
                'maintenance_alert_km' => $data['maintenance_alert_km'] ?? null,
                'payment_cutoff_day' => $data['payment_cutoff_day'] ?? 5,
                'document_alert_days' => $data['document_alert_days'] ?? 30,
                'license_alert_days' => $data['license_alert_days'] ?? 30,
                'operation_card_alert_days' => $data['operation_card_alert_days'] ?? 30,
                'soat_alert_days' => $data['soat_alert_days'] ?? 30,
                'rtm_alert_days' => $data['rtm_alert_days'] ?? 30,
                'activate_notifications' => $data['activate_notifications'] ?? false,
                'notify_by_email' => $data['notify_by_email'] ?? false,
                'notification_email' => $data['notification_email'] ?? null,
                'fuec_pdf_show_signatures' => $data['fuec_pdf_show_signatures'] ?? true,
                'fuec_pdf_show_contractor_details' => $data['fuec_pdf_show_contractor_details'] ?? true,
                'fuec_pdf_show_route_details' => $data['fuec_pdf_show_route_details'] ?? true,
                'vehicle_internal_number_counter' => $data['vehicle_internal_number_counter'] ?? 1,
                'fuec_enable_auto_internal_number' => $data['fuec_enable_auto_internal_number'] ?? false,
                'fuec_use_corporate_policies' => $data['fuec_use_corporate_policies'] ?? false,
                'corporate_rcc_insurer' => $data['corporate_rcc_insurer'] ?? null,
                'corporate_rce_expiration' => $data['corporate_rce_expiration'] ?? null,
                'platform_fee_type' => $data['platform_fee_type'] ?? 'VEHICLE_CLASS',
                'platform_fee_rates' => $data['platform_fee_rates'] ?? null,
            ]);
        });
    }


    public function updateSystemConfiguration(string $uuid, array $data): Model
    {
        return $this->transaction(function () use ($uuid, $data) {
            $record = $this->findByUuid($uuid);

            // Si no existe company_uuid en los datos, usamos el del registro para evitar perderlo
            $companyUuid = $data['company_uuid'] ?? $record->company_uuid;

            $record->update([
                'company_uuid' => $companyUuid,
                'fuec_require_daily_inspections' => $data['fuec_require_daily_inspections'] ?? $record->fuec_require_daily_inspections,
                'fuec_require_social_security' => $data['fuec_require_social_security'] ?? $record->fuec_require_social_security,
                'maintenance_alert_days' => $data['maintenance_alert_days'] ?? $record->maintenance_alert_days,
                'maintenance_numbers_days' => $data['maintenance_numbers_days'] ?? $record->maintenance_numbers_days,
                'maintenance_alert_km' => $data['maintenance_alert_km'] ?? $record->maintenance_alert_km,
                'payment_cutoff_day' => $data['payment_cutoff_day'] ?? $record->payment_cutoff_day,
                'document_alert_days' => $data['document_alert_days'] ?? $record->document_alert_days,
                'license_alert_days' => $data['license_alert_days'] ?? $record->license_alert_days,
                'operation_card_alert_days' => $data['operation_card_alert_days'] ?? $record->operation_card_alert_days,
                'soat_alert_days' => $data['soat_alert_days'] ?? $record->soat_alert_days,
                'rtm_alert_days' => $data['rtm_alert_days'] ?? $record->rtm_alert_days,
                'activate_notifications' => $data['activate_notifications'] ?? $record->activate_notifications,
                'notify_by_email' => $data['notify_by_email'] ?? $record->notify_by_email,
                'notification_email' => $data['notification_email'] ?? $record->notification_email,
                'fuec_pdf_show_signatures' => $data['fuec_pdf_show_signatures'] ?? $record->fuec_pdf_show_signatures,
                'fuec_pdf_show_contractor_details' => $data['fuec_pdf_show_contractor_details'] ?? $record->fuec_pdf_show_contractor_details,
                'fuec_pdf_show_route_details' => $data['fuec_pdf_show_route_details'] ?? $record->fuec_pdf_show_route_details,
                'vehicle_internal_number_counter' => $data['vehicle_internal_number_counter'] ?? $record->vehicle_internal_number_counter,
                'fuec_enable_auto_internal_number' => $data['fuec_enable_auto_internal_number'] ?? $record->fuec_enable_auto_internal_number,
                'fuec_use_corporate_policies' => $data['fuec_use_corporate_policies'] ?? $record->fuec_use_corporate_policies,
                'corporate_rcc_insurer' => $data['corporate_rcc_insurer'] ?? $record->corporate_rcc_insurer,
                'corporate_rce_expiration' => $data['corporate_rce_expiration'] ?? $record->corporate_rce_expiration,
                'platform_fee_type' => $data['platform_fee_type'] ?? $record->platform_fee_type,
                'platform_fee_rates' => $data['platform_fee_rates'] ?? $record->platform_fee_rates,
            ]);

            return $record->fresh();
        });
    }


    public function deleteSystemConfiguration(string $uuid): void
    {
        try {
            $this->delete($uuid);
        } catch (\Exception $e) {
            Logger::error('SystemConfigurationService@deleteSystemConfiguration: ' . $e->getMessage());
            throw $e;
        }
    }

    // ============================================================================
    // MÉTODOS DE SUBIDA DE IMÁGENES (Spatie MediaLibrary)
    // ============================================================================

    /**
     * Sube el logo del Ministerio de Transporte.
     */
    public function uploadMinistryLogo(string $uuid, $file): ?string
    {
        $record = $this->findByUuid($uuid);
        $record->addFile($file, 'MINISTRY_LOGO');
        $record->load('media');

        return $record->ministry_logo_url;
    }

    /**
     * Sube el logo de la Superintendencia.
     */
    public function uploadSuperLogo(string $uuid, $file): ?string
    {
        $record = $this->findByUuid($uuid);
        $record->addFile($file, 'SUPER_LOGO');
        $record->load('media');

        return $record->super_logo_url;
    }

    /**
     * Sube la hoja membretada.
     */
    public function uploadLetterhead(string $uuid, $file): ?string
    {
        $record = $this->findByUuid($uuid);
        $record->addFile($file, 'LETTERHEAD');
        $record->load('media');

        return $record->letterhead_url;
    }
}
