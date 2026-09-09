<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrations\TaxInformation;

use App\Traits\HandlesApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Request para validar la actualización de Información Tributaria.
 */
class UpdateTaxInformationRequest extends FormRequest
{
    use HandlesApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_uuid' => 'nullable|uuid|exists:companies,uuid',
            'is_withholding_agent_exempt' => 'nullable|boolean',
            'tax_special_regime' => 'nullable|string|max:255',
            'company_size' => 'nullable|string|max:50',
            'financial_statements_path' => 'nullable|string|max:255',
            'company_size_certificate_path' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator->errors()->toArray())
        );
    }
}
