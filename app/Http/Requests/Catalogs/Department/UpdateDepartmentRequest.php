<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\Department;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Departamento existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateDepartmentRequest extends FormRequest
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
            'dane_code' => ['nullable', 'string', 'max:5', 'unique:departments,dane_code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'dane_code.unique' => 'El código DANE ya se encuentra registrado.',
        ];
    }

    public function attributes(): array
    {
        return [
            'dane_code' => 'Código DANE',
            'name' => 'Nombre del Departamento',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
