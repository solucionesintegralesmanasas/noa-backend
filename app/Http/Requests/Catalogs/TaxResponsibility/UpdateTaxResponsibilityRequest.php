<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\TaxResponsibility;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de una Responsabilidad Tributaria existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateTaxResponsibilityRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:5', 'unique:tax_responsibilities,code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'El código de la responsabilidad ya se encuentra registrado.',
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
