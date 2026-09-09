<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet\ControlSheet;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateControlSheetRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_uuid' => ['sometimes', 'required', 'uuid', 'exists:vehicles,uuid'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
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
