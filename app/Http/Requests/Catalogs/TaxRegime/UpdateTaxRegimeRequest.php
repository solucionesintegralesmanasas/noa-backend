<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\TaxRegime;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Régimen Fiscal existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateTaxRegimeRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:10', 'unique:tax_regimes,code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'El código del régimen ya se encuentra registrado.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Código del Régimen',
            'name' => 'Nombre del Régimen',
            'description' => 'Descripción',
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
