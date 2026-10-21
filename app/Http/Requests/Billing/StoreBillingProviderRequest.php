<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Models\BillingProvider;
use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Registro de proveedor tecnológico (solo catálogo por ahora). */
class StoreBillingProviderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['nullable', 'string', 'max:30', 'in:'.implode(',', [BillingProvider::DRIVER_NULO, BillingProvider::DRIVER_APIDIAN])],
            'server_url' => ['nullable', 'url', 'max:200'],
            'test_set_id' => ['nullable', 'string', 'max:100'],
            'send_to_dian' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
            'client_id' => ['nullable', 'string', 'max:200'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'client_password' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'company_uuid' => ['nullable', 'uuid', 'exists:companies,uuid'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'Nombre', 'server_url' => 'URL del servicio'];
    }

    public function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
