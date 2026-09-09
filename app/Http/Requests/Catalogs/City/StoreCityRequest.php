<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\City;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva Ciudad.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreCityRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_uuid' => ['required', 'uuid', 'exists:departments,uuid'],
            'dane_code' => ['required', 'string', 'max:8'],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_uuid.required' => 'El departamento es obligatorio.',
            'department_uuid.exists' => 'El departamento seleccionado no es válido.',
            'dane_code.required' => 'El código DANE es obligatorio.',
            'name.required' => 'El nombre del municipio es obligatorio.',
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
