<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Edición de resolución de facturación. */
class UpdateBillingResolutionRequest extends FormRequest
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
            'resolution_number' => ['nullable', 'string', 'max:50'],
            'invoice_end_number' => ['sometimes', 'integer', 'min:1'],
            'end_date' => ['sometimes', 'date'],
            'billing_provider_uuid' => ['nullable', 'uuid', 'exists:billing_providers,uuid'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
