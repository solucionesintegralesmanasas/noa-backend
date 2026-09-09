<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\PucCommercial;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de una cuenta PUC existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdatePucCommercialRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:6'],
            'level' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:300'],
            'nature' => ['nullable', 'string', 'max:1'],
            'account_class' => ['nullable', 'string', 'max:50'],
            'is_reductive' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
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
