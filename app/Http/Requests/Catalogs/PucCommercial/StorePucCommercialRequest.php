<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\PucCommercial;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva cuenta PUC.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StorePucCommercialRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:6'],
            'level' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:300'],
            'nature' => ['required', 'string', 'max:1'],
            'account_class' => ['required', 'string', 'max:50'],
            'is_reductive' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código contable es obligatorio.',
            'level.required' => 'El nivel de la cuenta es obligatorio.',
            'description.required' => 'La descripción de la cuenta es obligatoria.',
            'nature.required' => 'La naturaleza de la cuenta (D/C) es obligatoria.',
            'account_class.required' => 'La clase contable es obligatoria.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Código PUC',
            'level' => 'Nivel',
            'description' => 'Denominación',
            'nature' => 'Naturaleza',
            'account_class' => 'Clase Contable',
            'is_reductive' => 'Cuenta Reductora',
            'is_active' => 'Vigente',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
