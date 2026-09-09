<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\VehicleClass;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva Clase de Vehículo.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreVehicleClassRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_code_class' => ['required', 'string', 'max:5'],
            'description' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'class_code_class.required' => 'El código corto de la clase es obligatorio.',
            'description.required' => 'La descripción técnica es obligatoria.',
        ];
    }

    public function attributes(): array
    {
        return [
            'class_code_class' => 'Código de Clase',
            'description' => 'Descripción',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
