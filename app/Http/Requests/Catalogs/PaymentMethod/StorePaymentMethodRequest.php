<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\PaymentMethod;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la creación de un nuevo Medio de Pago.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class StorePaymentMethodRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dian_code' => ['required', 'string', 'max:5', 'unique:payment_methods,dian_code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dian_code.required' => 'El código DIAN es obligatorio.',
            'dian_code.unique' => 'El código DIAN ya se encuentra registrado.',
            'name.required' => 'El nombre del medio de pago es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'dian_code' => 'Código DIAN',
            'name' => 'Nombre del Medio de Pago',
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
