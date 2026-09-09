<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\BillingResolutionType;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Tipo de Resolución.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreBillingResolutionTypeRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'unique:billing_resolution_types,code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código DIAN es obligatorio.',
            'code.unique' => 'El código DIAN ya se encuentra registrado.',
            'name.required' => 'El nombre del tipo de documento es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Código DIAN',
            'name' => 'Nombre del Tipo de Documento',
            'is_active' => 'Estado',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
