<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\City;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de una Ciudad existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateCityRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_uuid' => ['nullable', 'uuid', 'exists:departments,uuid'],
            'dane_code' => ['nullable', 'string', 'max:8'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_uuid.exists' => 'El departamento seleccionado no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'department_uuid' => 'Departamento',
            'dane_code' => 'Código DANE',
            'name' => 'Nombre del Municipio',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
