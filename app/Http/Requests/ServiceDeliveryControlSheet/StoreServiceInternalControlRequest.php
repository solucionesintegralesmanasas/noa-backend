<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreServiceInternalControlRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid'],
            'vehicle_uuid' => ['required', 'uuid', 'exists:vehicles,uuid'],
            'third_party_uuid' => ['required', 'uuid', 'exists:third_parties,uuid'],
            'fuec_uuid' => ['nullable', 'uuid', 'exists:fuecs,uuid'],
            'service_delivery_control_sheet_uuid' => ['required', 'uuid', 'exists:service_delivery_control_sheet,uuid'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_uuid.required' => 'El campo empresa es obligatorio.',
            'company_uuid.uuid' => 'El campo empresa debe ser un UUID válido.',
            'company_uuid.exists' => 'La empresa seleccionada no existe.',
            'vehicle_uuid.required' => 'El campo vehículo es obligatorio.',
            'vehicle_uuid.uuid' => 'El campo vehículo debe ser un UUID válido.',
            'vehicle_uuid.exists' => 'El vehículo seleccionado no existe.',
            'third_party_uuid.required' => 'El campo conductor es obligatorio.',
            'third_party_uuid.uuid' => 'El campo conductor debe ser un UUID válido.',
            'third_party_uuid.exists' => 'El conductor seleccionado no existe.',
            'fuec_uuid.uuid' => 'El campo FUEC debe ser un UUID válido.',
            'fuec_uuid.exists' => 'El FUEC seleccionado no existe.',
            'service_delivery_control_sheet_uuid.required' => 'El campo hoja de control es obligatorio.',
            'service_delivery_control_sheet_uuid.uuid' => 'El campo hoja de control debe ser un UUID válido.',
            'service_delivery_control_sheet_uuid.exists' => 'La hoja de control seleccionada no existe.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'empresa',
            'vehicle_uuid' => 'vehículo',
            'third_party_uuid' => 'conductor',
            'fuec_uuid' => 'FUEC',
            'service_delivery_control_sheet_uuid' => 'hoja de control',
            'is_active' => 'estado activo',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
