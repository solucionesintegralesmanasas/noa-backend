<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
class UpdateSystemConfigurationRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uuid = $this->route('uuid');

        return [
            'company_uuid' => 'sometimes|uuid|exists:companies,uuid|unique:system_configuration,company_uuid,'.$uuid.',uuid',
            'fuec_require_daily_inspections' => 'sometimes|boolean',
            'fuec_require_social_security' => 'sometimes|boolean',
            'maintenance_alert_days' => 'nullable|integer|min:0|max:255',
            'maintenance_numbers_days' => 'nullable|string|max:3',
            'maintenance_alert_km' => 'nullable|integer|min:0',
            'payment_cutoff_day' => 'sometimes|integer|min:1|max:31',
            'document_alert_days' => 'sometimes|integer|min:0|max:255',
            'license_alert_days' => 'sometimes|integer|min:0|max:255',
            'operation_card_alert_days' => 'sometimes|integer|min:0|max:255',
            'soat_alert_days' => 'sometimes|integer|min:0|max:255',
            'rtm_alert_days' => 'sometimes|integer|min:0|max:255',
            'activate_notifications' => 'sometimes|boolean',
            'notify_by_email' => 'sometimes|boolean',
            'notification_email' => 'nullable|email|max:191',
            'fuec_pdf_show_signatures' => 'nullable|boolean',
            'fuec_pdf_show_contractor_details' => 'nullable|boolean',
            'fuec_pdf_show_route_details' => 'nullable|boolean',
            'vehicle_internal_number_counter' => 'nullable|integer|min:1',
            'fuec_enable_auto_internal_number' => 'nullable|boolean',
            'fuec_use_corporate_policies' => 'nullable|boolean',
            'corporate_rcc_insurer' => 'nullable|string|max:100',
            'corporate_rce_expiration' => 'nullable|date_format:Y-m-d',
            'platform_fee_type' => 'sometimes|in:PASSENGER_RANGE,VEHICLE_CLASS',
            'platform_fee_rates' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.exists' => 'La empresa especificada no existe.',
            'company_uuid.unique' => 'Ya existe una configuración registrada para esta empresa.',
            'payment_cutoff_day.integer' => 'El día de corte debe ser un número entero.',
            'payment_cutoff_day.min' => 'El día de corte debe ser mínimo 1.',
            'payment_cutoff_day.max' => 'El día de corte debe ser máximo 31.',
            'maintenance_alert_km.integer' => 'La alerta por kilometraje debe ser un número entero.',
            'maintenance_alert_km.min' => 'La alerta por kilometraje no puede ser menor a 0.',
            'notification_email.email' => 'El correo electrónico de notificación debe ser una dirección válida.',
            'vehicle_internal_number_counter.integer' => 'El contador de número interno debe ser un número entero.',
            'vehicle_internal_number_counter.min' => 'El contador de número interno debe ser mínimo 1.',
            'corporate_rcc_insurer.max' => 'El nombre de la aseguradora para pólizas corporativas no puede superar los 100 caracteres.',
            'corporate_rce_expiration.date_format' => 'La fecha de vencimiento de las pólizas corporativas debe tener el formato YYYY-MM-DD.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
