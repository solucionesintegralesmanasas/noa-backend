<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\Withholding;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de una Retención existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateWithholdingRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:20', 'unique:withholdings,code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', 'in:RETEFUENTE,RETEIVA,RETEICA,RETECREE,AUTORETENCIÓN'],
            'dian_concept' => ['nullable', 'string', 'max:10'],
            'base_minimum' => ['nullable', 'numeric'],
            'rate' => ['nullable', 'numeric'],
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
            'code.unique' => 'El código de la retención ya se encuentra registrado.',
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
