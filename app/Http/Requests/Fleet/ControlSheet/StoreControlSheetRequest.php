<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\ControlSheet;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreControlSheetRequest extends FormRequest
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
            'observations' => ['nullable', 'string', 'max:1000'],
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
            'observations.string' => 'Las observaciones deben ser una cadena de texto.',
            'observations.max' => 'Las observaciones no deben exceder los 1000 caracteres.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_uuid' => 'empresa',
            'vehicle_uuid' => 'vehículo',
            'observations' => 'observaciones',
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
