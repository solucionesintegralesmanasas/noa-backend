<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\Withholding;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de una nueva Retención.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreWithholdingRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:withholdings,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:RETEFUENTE,RETEIVA,RETEICA,RETECREE,AUTORETENCIÓN'],
            'dian_concept' => ['nullable', 'string', 'max:10'],
            'base_minimum' => ['nullable', 'numeric'],
            'rate' => ['required', 'numeric'],
            'debit_account' => ['nullable', 'string', 'max:20'],
            'credit_account' => ['nullable', 'string', 'max:20'],
            'applies_purchases' => ['nullable', 'boolean'],
            'applies_sales' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código interno de la retención es obligatorio.',
            'code.unique' => 'El código de la retención ya se encuentra registrado.',
            'name.required' => 'El nombre de la retención es obligatorio.',
            'type.required' => 'El tipo de retención es obligatorio.',
            'rate.required' => 'El porcentaje de retención es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Código Retención',
            'name' => 'Denominación',
            'type' => 'Tipo',
            'dian_concept' => 'Concepto DIAN',
            'base_minimum' => 'Base Mínima',
            'rate' => 'Tarifa',
            'debit_account' => 'Cuenta Débito',
            'credit_account' => 'Cuenta Crédito',
            'applies_purchases' => 'Aplica Compras',
            'applies_sales' => 'Aplica Ventas',
            'is_active' => 'Activa',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
