<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\TaxType;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Tipo de Impuesto existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdateTaxTypeRequest extends FormRequest
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
            'dian_code' => ['nullable', 'string', 'max:10', 'unique:tax_types,dian_code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:IVA,INC,ICA,TIMBRE,FOMENTO,OTRO'],
            'rate' => ['nullable', 'numeric'],
            'debit_account' => ['nullable', 'string', 'max:20'],
            'credit_account' => ['nullable', 'string', 'max:20'],
            'applies_sales' => ['nullable', 'boolean'],
            'applies_purchases' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dian_code.unique' => 'El código DIAN ya se encuentra registrado.',
        ];
    }

    public function attributes(): array
    {
        return [
            'dian_code' => 'Código DIAN',
            'name' => 'Nombre del Impuesto',
            'type' => 'Clasificación',
            'rate' => 'Tarifa',
            'debit_account' => 'Cuenta Débito',
            'credit_account' => 'Cuenta Crédito',
            'applies_sales' => 'Aplica Ventas',
            'applies_purchases' => 'Aplica Compras',
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
