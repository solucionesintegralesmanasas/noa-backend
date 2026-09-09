<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs\PaymentMethod;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Solicitud de validación para la actualización de un Medio de Pago existente.
 *
 * @author Darwin Montes
 *
 * @version 1.0.0
 */
class UpdatePaymentMethodRequest extends FormRequest
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
            'dian_code' => ['nullable', 'string', 'max:5', 'unique:payment_methods,dian_code,'.$uuid.',uuid'],
            'name' => ['nullable', 'string', 'max:100'],
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
