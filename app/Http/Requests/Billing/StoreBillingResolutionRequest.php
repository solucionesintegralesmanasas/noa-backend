<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Creación de resolución de facturación (rango interno por ahora). */
class StoreBillingResolutionRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_uuid' => ['required', 'uuid', 'exists:companies,uuid'],
            'branch_uuid' => ['nullable', 'uuid', 'exists:branches,uuid'],
            'resolution_type_code' => ['required', 'string', 'exists:billing_resolution_types,code'],
            'resolution_number' => ['nullable', 'string', 'max:50'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'invoice_start_number' => ['required', 'integer', 'min:1'],
            'invoice_end_number' => ['required', 'integer', 'gt:invoice_start_number'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'billing_provider_uuid' => ['nullable', 'uuid', 'exists:billing_providers,uuid'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'resolution_type_code' => 'Tipo de resolución',
            'invoice_prefix' => 'Prefijo',
            'invoice_start_number' => 'Numeración inicial',
            'invoice_end_number' => 'Numeración final',
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
