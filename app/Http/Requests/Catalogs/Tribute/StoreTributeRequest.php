<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\Tribute;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Tributo.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreTributeRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dian_code' => ['required', 'string', 'max:10', 'unique:tributes,dian_code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dian_code.required' => 'El código DIAN del tributo es obligatorio.',
            'dian_code.unique' => 'El código DIAN ya se encuentra registrado.',
            'name.required' => 'El nombre del tributo es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'dian_code' => 'Código DIAN',
            'name' => 'Nombre Tributo',
            'description' => 'Descripción',
            'is_active' => 'Habilitado',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
