<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceDeliveryControlSheet;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateServiceInternalControlSubcontractedRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_class_uuid' => ['sometimes', 'required', 'uuid', 'exists:vehicle_classes,uuid'],
            'service_delivery_control_sheet_uuid' => ['sometimes', 'required', 'uuid', 'exists:service_delivery_control_sheet,uuid'],
            'vehicle_license_plate' => ['sometimes', 'required', 'string', 'max:20'],
            'driver_name_and_surname' => ['sometimes', 'required', 'string', 'max:255'],
            'driver_license_number' => ['sometimes', 'required', 'string', 'max:20'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_class_uuid.required' => 'El campo clase de vehículo es obligatorio.',
            'vehicle_class_uuid.uuid' => 'El campo clase de vehículo debe ser un UUID válido.',
            'vehicle_class_uuid.exists' => 'La clase de vehículo seleccionada no existe.',
            'service_delivery_control_sheet_uuid.required' => 'El campo hoja de control es obligatorio.',
            'service_delivery_control_sheet_uuid.uuid' => 'El campo hoja de control debe ser un UUID válido.',
            'service_delivery_control_sheet_uuid.exists' => 'La hoja de control seleccionada no existe.',
            'vehicle_license_plate.required' => 'El campo placa del vehículo es obligatorio.',
            'vehicle_license_plate.string' => 'La placa del vehículo debe ser una cadena de texto.',
            'vehicle_license_plate.max' => 'La placa del vehículo no debe exceder los 20 caracteres.',
            'driver_name_and_surname.required' => 'El campo nombre y apellido del conductor es obligatorio.',
            'driver_name_and_surname.string' => 'El nombre y apellido del conductor debe ser una cadena de texto.',
            'driver_name_and_surname.max' => 'El nombre y apellido del conductor no debe exceder los 255 caracteres.',
            'driver_license_number.required' => 'El campo número de licencia del conductor es obligatorio.',
            'driver_license_number.string' => 'El número de licencia del conductor debe ser una cadena de texto.',
            'driver_license_number.max' => 'El número de licencia del conductor no debe exceder los 20 caracteres.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_class_uuid' => 'clase de vehículo',
            'service_delivery_control_sheet_uuid' => 'hoja de control',
            'vehicle_license_plate' => 'placa del vehículo',
            'driver_name_and_surname' => 'nombre y apellido del conductor',
            'driver_license_number' => 'número de licencia del conductor',
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
