<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\TaxType;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Tipo de Impuesto.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StoreTaxTypeRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dian_code' => ['required', 'string', 'max:10', 'unique:tax_types,dian_code'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:IVA,INC,ICA,TIMBRE,FOMENTO,OTRO'],
            'rate' => ['required', 'numeric'],
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
            'dian_code.required' => 'El código DIAN del impuesto es obligatorio.',
            'dian_code.unique' => 'El código DIAN ya se encuentra registrado.',
            'name.required' => 'El nombre del impuesto es obligatorio.',
            'type.required' => 'La clasificación tributaria es obligatoria.',
            'rate.required' => 'La tarifa porcentual es obligatoria.',
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
