<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\BankDetail;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la creación de un Detalle Bancario.
 */
class StoreBankDetailRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'required|uuid|exists:companies,uuid',
            'bank_name' => 'nullable|string|max:100',
            'branch_office' => 'nullable|string|max:100',
            'account_type' => 'nullable|string|max:50',
            'account_number' => 'required|string|max:50',
            'account_holder' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.required' => 'El número de cuenta bancaria es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_name' => 'Nombre del Banco',
            'account_number' => 'Número de Cuenta',
            'account_holder' => 'Titular de la Cuenta',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
