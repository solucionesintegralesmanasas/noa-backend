<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\VehicleClass;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de una Clase de Vehículo existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateVehicleClassRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_code_class' => ['nullable', 'string', 'max:5'],
            'description' => ['nullable', 'string', 'max:200'],
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
