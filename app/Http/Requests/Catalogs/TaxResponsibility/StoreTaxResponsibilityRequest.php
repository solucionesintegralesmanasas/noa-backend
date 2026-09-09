<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\TaxResponsibility;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva Responsabilidad Tributaria.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreTaxResponsibilityRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:5', 'unique:tax_responsibilities,code'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de la responsabilidad es obligatorio.',
            'code.unique' => 'El código de la responsabilidad ya se encuentra registrado.',
            'name.required' => 'El nombre de la responsabilidad es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Código de la Responsabilidad',
            'name' => 'Nombre de la Responsabilidad',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
